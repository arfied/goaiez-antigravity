<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Contracts\ReachesRecipients;
use App\Contracts\SendLogReader;
use App\Contracts\Texter;
use App\Enums\CarrierVerdict;
use App\Exceptions\TextNotDeliverable;
use App\Services\Config\DefaultsRegistry;
use App\Support\PlatformCredentials;
use App\Support\VendorLog;
use Carbon\Exceptions\InvalidFormatException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * SMS over Infobip.
 *
 * Endpoint, headers, request shape and response shape read on 2026-08-09 from
 * **Infobip's own generated PHP client** — `Infobip/Api/SmsApi.php`,
 * `Infobip/Configuration.php` and the `Sms*` models in `infobip-api-php-client`
 * — rather than from a documentation page or from memory. `CLAUDE.md`'s standing
 * rule is to verify a vendor string against the raw artefact, and this vendor
 * makes that rule earn its place twice over:
 *
 *   - **`POST /sms/3/messages`, not `/sms/2/text/advanced`.** The v2 path is
 *     what a decade of tutorials, blog posts and answers still show, and it is
 *     the one a from-memory implementation writes. `SmsApi::sendSmsMessages()`
 *     builds `/sms/3/messages`; the `/sms/1/*` paths that share the file are
 *     bulk scheduling and preview, not sending.
 *   - **`Authorization: App <key>`, not `Bearer`.** `Configuration::API_KEY_PREFIX`
 *     is the literal string `App`. `Http::withToken()` would write `Bearer` and
 *     produce a 401 that reads like a bad key, sending whoever is debugging it
 *     to rotate a credential that was correct.
 *
 * ## MMS — read twice, from two different artefacts, and they disagreed
 *
 * ⛔ **THIS SECTION STATED A WIRE SHAPE THAT DOES NOT EXIST, AS VERIFIED FACT,
 * AND IT WAS THE FIRST DOCBLOCK IN THE FILE** (4496–4498). It said
 * `MmsOutboundTextSegment` is `{type: TEXT, text}` and listed
 * `MmsOutboundSegmentType::ALLOWED_VALUES` — and {@see self::segments()} at the
 * bottom of the file said the opposite, having been corrected. **The next lane
 * greps the top of the file, finds `type`, and puts it back.**
 *
 * ⚠️ **BOTH READINGS ARE CARRIED HERE RATHER THAN ONE OF THEM DELETED**, on
 * 4368's rule, because the mistake is worth more than the correction:
 *
 *   - **2026-08-12 (2542, 2543).** Read from `infobip-api-php-client` at
 *     `master` — `Infobip/Api/MmsApi.php` and the `Mms*` models. That client
 *     carries `type` on every segment model and a `MmsOutboundSegmentType` enum
 *     of `TEXT LINK SMIL UPLOADED_REFERENCE`. It was read carefully and it was
 *     not misread.
 *   - **2026-08-16 (4261, 4385–4388), and this is the authority.** Read from the
 *     published request schema at
 *     `https://www.infobip.com/docs/api/channels/mms/send-mms/send-mms-message.md`.
 *     `MmsAdvancedMessageSegment` is an `anyOf` over five options and **not one
 *     of them carries a `type`**; `grep -c '"type"'` over the whole reference
 *     returns `0`. Which option a segment is, is carried by its keys alone —
 *     `text` makes it Text, `contentUrl` makes it Link.
 *
 * ⚠️ **A GENERATED CLIENT IS AN ARTEFACT AND IS STILL NOT THE PROTOCOL.** Those
 * enum values are model **discriminators**, which an OpenAPI generator
 * synthesises so it can pick a class when deserialising an `anyOf`. `CLAUDE.md`
 * says to verify a vendor string against the raw artefact; this is the second
 * edge of that rule, and it is why the endpoint and envelope facts below — which
 * came from the same client — are worth keeping and the segment shape was not.
 *
 * What that client is still the source for, unchanged and unchallenged by the
 * schema:
 *
 *   - **`POST /mms/2/messages`**, from `MmsApi::sendMmsMessagesRequest()`'s
 *     `$resourcePath`, with `Content-Type: application/json`. ⛔ **It is a
 *     different endpoint and a different major version from SMS** — `/sms/3/…`
 *     against `/mms/2/…` — so a driver that merely added a field to the SMS
 *     body would post media to an endpoint that does not carry it. The `/mms/1/*`
 *     paths that share the file are the inbox and the binary upload.
 *   - **The envelope is identical to SMS**: `MmsRequest` is
 *     `{ messages: [...] }`, and `MmsMessage` is `sender` + `destinations[].to`
 *     + `content` + `webhooks` — same field names, same shapes, same
 *     `webhooks.callbackData` with the same 4000-character bound.
 *   - **The content is not.** `MmsOutboundContent` is `messageSegments[]`, and
 *     ⛔ **the segments are an `anyOf` distinguished by their keys, NOT a
 *     discriminated union with a `type`** — a text segment is `{text}` and a
 *     media segment is `{contentUrl, contentType?}`. See the correction at the
 *     head of this docblock and the full argument on {@see self::segments()};
 *     `UPLOADED_REFERENCE` is the option that pairs with `/mms/1/content`, which
 *     is the upload path this application does not take.
 *   - ✅ **The *response* is the same object.** `sendMmsMessages()` returns
 *     `MessageResponse` — `bulkId` plus `messages[]` of `MessageResponseDetails`
 *     (`messageId`, `status.groupName`, `status.name`) — which is the same
 *     shape `SmsResponse` carries. That is why {@see self::accepted()} reads
 *     both without a branch, and it is a verified fact rather than a convenient
 *     assumption.
 *
 * ⛔ **`options.deliveryTimeWindow` AND `options.schedule` EXIST ON BOTH
 * PRODUCTS AND ARE DELIBERATELY NOT USED** (2204). A vendor-side window cannot
 * resolve a recipient's local timezone, and a vendor holding a message would
 * silently quiet-gate the transactional sends the T69 law forbids gating.
 *
 * ⚠️ **`destinations[].messageId` IS A CALLER-SUPPLIED ID AND IS STILL NOT
 * USED** (2545). It is the obvious place to put a `SendKey` for vendor-side
 * deduplication, and `SendDriver`'s docblock wants one — but nothing in the
 * generated client or its models says Infobip *dedupes* on it, and a field
 * described only as an id would be an idempotency guarantee this application
 * invented on somebody else's behalf. The application-side unique index is
 * therefore the whole mechanism, and the docblock now says so.
 *
 * ✅ **2545 RE-VERIFIED AGAINST THE VENDOR'S OWN OPENAPI SPECIFICATION AND IT
 * HOLDS — 2026-08-21 (7065).** Not a documentation page and not the generated
 * client this time: `https://api.infobip.com/platform/1/openapi/sms`, version
 * **`3.222.1`**, `x-generatedAt` **2026-08-20T08:26:36Z**, 298,706 bytes,
 * downloaded whole and grepped rather than summarised. **`idempot`, `Idempot`,
 * `dedup`, `duplicate` and `x-request` each occur ZERO times in it**, and
 * `POST /sms/3/messages` declares **no header parameters at all**
 * (`"parameters": []`). So there is no vendor-side mechanism to negotiate, and
 * a retry after an unknown outcome is a second message by construction.
 *
 * ⛔ **BUT THE FIELD IS NOT USELESS, AND THE SECOND USE IS NOT IDEMPOTENCY**
 * (7066). The same specification describes `Destination.messageId` as *"The ID
 * that uniquely identifies the message sent. Anything above 200 characters
 * passed in the request will be clipped during processing and returned in
 * response, reports and logs"*, `writeOnly`, and `SMSResponseDetails.messageId`
 * as *"Unique message ID. **If not passed, it will be automatically generated**
 * and returned in a response."* — and `GET /sms/3/logs` takes a **`messageId`**
 * query filter (*"Unique message ID for which a log is requested… may contain
 * multiple comma-separated values"*), over *"logs… generated in the last 48
 * hours"*. **So supplying our own id would not make a retry safe; it would make
 * the unknown outcome ANSWERABLE within 48 hours**, by asking the vendor
 * whether it holds the message. That is a strictly different claim from an
 * idempotency guarantee, it needs no promise Infobip has not made, and it is
 * the thing that would actually close this gap. **It is not built here** — it
 * widens {@see Texter} and needs somewhere durable to keep the
 * id across a rolled-back transaction — and 7066 records it as owed.
 *
 * ⚠️ **THIS CLASS IS ONE OF TWO DRIVERS AND IS NOT THE DEFAULT.** `SMS_DRIVER`
 * seeds `log` ({@see LogTexter}), so nothing reaches Infobip until an operator
 * names this driver *and* {@see PlatformTexter}'s `sms.enabled` switch is turned
 * on — and that switch waits on slice 2's STOP handling (1567), because **you
 * may not send what you cannot stop**. Two independent things, deliberately: the
 * driver is a deployment fact and the switch is an operational one, and either
 * alone sends nothing.
 *
 * ⚠️ **ON LARAVEL'S HTTP CLIENT, NEVER `infobip/infobip-api-php-client`**, even
 * though the client above was read to write this. `config/credentials.php` gave
 * the instruction before there was anything to instruct — decision 277's
 * reasoning, one vendor over: `Http::preventStrayRequests()` and `40` Part 8's
 * outbound lint both see through `Http::`, and neither would see an SDK's own
 * bundled Guzzle. On the vendor billing per message that is not a style
 * preference. It is also what keeps this file inside
 * `outboundHttpPermittedFiles()`, which is the list that ties reaching a new
 * host to naming it in `SUBPROCESSOR-INVENTORY.md`.
 *
 * ⚠️ **NO SYNCHRONOUS RETRY.** One attempt, then a classified failure, with
 * retrying left to the job that has backoff — `ZernioGbpClient`'s posture, and
 * the more important one here: a retried SMS that actually succeeded the first
 * time is a second message to a real person, not a duplicated read.
 *
 * ⛔ **AND THE FAILURE IS NOW GENUINELY CLASSIFIED, WHICH THAT SENTENCE HAS
 * CLAIMED SINCE THE DAY IT WAS WRITTEN AND THE CODE DID NOT DO** (7060). The
 * catch below read `} catch (ConnectionException) {` with **no variable bound
 * at all** and threw one outcome for every cause, so *"the request never left
 * this machine"* and *"the carrier may already have taken the message"* arrived
 * at every caller identically — and **both released the idempotency claim**,
 * which is the only thing between a queued retry and a second text to a member
 * of the public. `PlatformMessageSender` had named that exact risk in its own
 * docblock and three waves read it and left it. **The paragraph asserting a
 * classification is what stopped anybody checking for one** — `CLAUDE.md`
 * 314–316, in the file that already quotes 314–316 twice.
 *
 * ⛔ **`ReachesRecipients` IS THIS CLASS'S CLAIM THAT A MESSAGE LEAVES THE
 * PLATFORM, AND IT IS THE ONLY DRIVER THAT MAY MAKE IT** (11300). Every return
 * path in {@see self::send()} either throws or is a 200 whose per-message
 * status was not in {@see self::REFUSED_GROUPS} — so a `SentText` from here
 * means Infobip took the message, and `LogTexter` returning one means nothing
 * of the kind. ⚠️ **It is still not a delivery**, and declaring the interface
 * changes nothing about that: a message accepted and then filtered because the
 * sender is not the number the 10DLC campaign was registered against is
 * invisible to this application, which is what {@see self::sender()}'s own
 * docblock says.
 */
final class InfobipClient implements ReachesRecipients, SendLogReader, Texter
{
    /**
     * Infobip's own prefix, verbatim from `Configuration::API_KEY_PREFIX`.
     *
     * A constant rather than an inline string because the wrong value here is
     * the failure this class's docblock singles out as misdiagnosable.
     */
    private const string AUTH_PREFIX = 'App';

    /**
     * The status group in which the carrier would not take the message at all.
     *
     * ⛔ **`REJECTED` MEANS NOTHING ENTERED THE NETWORK** — a blocked handset,
     * an unregistered sender, a campaign that has not cleared. Nobody is
     * holding the message and ordinarily nobody billed us for it, which is what
     * makes it the one answer a reconciler may treat as final.
     *
     * @var list<string>
     */
    private const array DECLINED_GROUPS = ['REJECTED'];

    /**
     * The status group in which the carrier took the message and could not
     * deliver it.
     *
     * ⛔ **IT IS NOT THE SAME THING AS THE ONE ABOVE AND WAS TREATED AS THE
     * SAME THING UNTIL 2026-08-22** (7500). `UNDELIVERABLE` is a message this
     * carrier **accepted**: it entered the network, it was ordinarily billed,
     * and the person may be holding it. On the way *out* that still means *"do
     * not treat this as sent"*; on the way *back* it means the opposite of
     * `REJECTED`, and one list could not say both.
     *
     * @var list<string>
     */
    private const array UNDELIVERED_GROUPS = ['UNDELIVERABLE'];

    /**
     * The status groups in which a message was not taken.
     *
     * From Infobip's documented `status.groupName` vocabulary. Named rather than
     * matched positively — an unknown group name is treated as accepted and the
     * delivery receipt corrects it, because refusing on an unrecognised label
     * would turn a vendor adding a state into an outage.
     *
     * ⚠️ `PENDING` is an acceptance and must not be added here: it is what a
     * successful submission actually answers, alongside `status.name` of
     * `PENDING_ACCEPTED`.
     *
     * ⚠️ **DERIVED FROM THE TWO CONSTANTS ABOVE RATHER THAN RESTATED**, so the
     * submission path refuses exactly what it refused before and cannot drift
     * from the pair the log path reads. **This is still the list that decides a
     * submission**: both groups mean *"do not treat this as sent"* in a response
     * to a request we just made, and 7500 changed nothing about that question.
     *
     * @var list<string>
     */
    private const array REFUSED_GROUPS = [...self::DECLINED_GROUPS, ...self::UNDELIVERED_GROUPS];

    /**
     * The libcurl codes that establish the request never left this process.
     *
     * ⛔ **TWO, AND THE SHORTNESS OF THE LIST IS THE POINT** (7061). Read
     * verbatim from `https://curl.se/libcurl/c/libcurl-errors.html`, the raw
     * page rather than a summary, `last-modified: Tue, 04 Aug 2026 08:57:36
     * GMT`, re-fetched **2026-08-21** for this slice:
     *
     *   6  `CURLE_COULDNT_RESOLVE_HOST` — *"Could not resolve host. The given
     *      remote host was not resolved."* No address means no socket and no
     *      request bytes.
     *   7  `CURLE_COULDNT_CONNECT` — *"Failed to connect() to host or proxy."*
     *      `connect()` did not return, so no request bytes exist.
     *
     * ⛔ **28 `CURLE_OPERATION_TIMEDOUT` IS NOT IN THIS LIST AND IT IS THE
     * LIKELIEST CAUSE OF A DUPLICATE TEXT.** Its own wording is *"the specified
     * time-out period was reached according to the conditions"*, and
     * `CURLOPT_CONNECTTIMEOUT`'s page names **no error code at all** (fetched
     * 2026-08-21) — so a request that timed out *before* connecting is
     * indistinguishable here from one that timed out waiting for Infobip's
     * answer, and the only thing that differs is a third party's message text.
     * **A send that a carrier accepted and did not get to acknowledge is
     * therefore still an unknown, and this allowlist does not narrow it.**
     * 52 `GOT_NOTHING` (the server was reached), 35 `SSL_CONNECT_ERROR` (the
     * TCP connection stood) and 5 `COULDNT_RESOLVE_PROXY` (no proxy is
     * configured anywhere in this application, so the arm has no instance —
     * 256) are all out for their own reasons.
     *
     * ⛔ **AND THE CATCH IS FAR WIDER THAN GUZZLE'S FIVE-MEMBER CONNECTION MAP**
     * (7003, verified again here). `vendor/guzzlehttp/guzzle/src/Handler/CurlFactory.php`
     * turns 28, 6, 7, 35 and 52 into a `ConnectException` — but
     * `Illuminate\Http\Client\PendingRequest::marshalRequestExceptionWithoutResponse()`
     * funnels **every** `RequestException` carrying no response into the same
     * class. That is every other libcurl code — 18 `PARTIAL_FILE`, 55
     * `SEND_ERROR`, 56 `RECV_ERROR`, 92 `HTTP2_STREAM` — and **nearly all of
     * those mean the request did leave.** An allowlist of two is the only shape
     * that is correct; a denylist of Guzzle's three would classify `RECV_ERROR`
     * as never-sent and text somebody twice.
     *
     * @var list<int>
     */
    private const array NEVER_LEFT = [6, 7];

    /**
     * The reason recorded when a transport failure may have reached the carrier.
     *
     * Unchanged from the single string every outcome carried before 7060,
     * deliberately: it is the safe default, and an operator diffing an old
     * `automation_runs` row against a new one should see the same token for the
     * same outcome.
     */
    private const string REASON_UNKNOWN = 'connection_failed';

    /**
     * The reason recorded when the request provably never left this process.
     */
    private const string REASON_NEVER_LEFT = 'connection_never_established';

    /**
     * The prefix on a handle for a message that went out as an SMS, and the one
     * for an MMS.
     *
     * ⛔ **THE HANDLE SAYS WHICH PRODUCT IT WAS, BECAUSE THE ONLY THING THAT
     * KNOWS IS THE METHOD THAT MINTS IT.** `SmsSendDriver`'s docblock states the
     * property this preserves: *"`InfobipClient` picks `/mms/2/messages` over
     * `/sms/3/messages` from whether the list is empty, and nothing above the
     * transport knows there were two endpoints."* The logs are two endpoints as
     * well — `GET /sms/3/logs` and `GET /mms/2/logs` — so something has to
     * choose again on the way back, and the alternative was for the reconciler
     * to re-derive the product from `campaign_recipients.media_path`.
     *
     * ⚠️ **THAT ALTERNATIVE IS A SECOND DERIVATION THAT CAN DISAGREE WITH THE
     * FIRST**, and it has a real instance: a row whose rendered image has since
     * been deleted from the disk and whose re-render then fails for want of a
     * first name is sent as a plain SMS with `media_path` still populated. The
     * prefix cannot disagree, because it is written by the same expression that
     * picked the endpoint.
     *
     * ⚠️ **AND NOTHING OUTSIDE THIS CLASS PARSES IT.** {@see self::outcomesFor()}
     * is where the prefix is read; the reconciler hands over a list of strings
     * and is told what became of each, product-blind.
     */
    private const string SMS_HANDLE_PREFIX = 'sms-';

    private const string MMS_HANDLE_PREFIX = 'mms-';

    /**
     * How many handles may travel in one `messageId` filter.
     *
     * ⚠️ **DERIVED FROM THE VENDOR'S DOCUMENTED CEILING RATHER THAN CHOSEN.**
     * `GET /sms/3/logs`'s `messageId` parameter is *"May contain multiple
     * comma-separated values. **Maximum length 2048 characters**"* (raw OpenAPI,
     * version `3.222.1`, fetched 2026-08-22). A handle is a four-character
     * prefix plus a UUID — forty characters — so forty-one with its separator,
     * and forty of them is 1,640: comfortably inside, with room for the day a
     * handle grows.
     *
     * ⛔ **A CHUNK THAT OVERRAN WOULD BE CLIPPED BY THE VENDOR, NOT REFUSED**,
     * which is the failure mode worth the headroom: the last few handles in the
     * request would silently go unasked and their rows would stay `Unknown`
     * with nothing anywhere saying why.
     */
    public const int LOG_LOOKUP_CHUNK = 40;

    public function logLookupChunk(): int
    {
        return app(DefaultsRegistry::class)->int('sms.infobip.log_lookup_chunk');
    }

    /**
     * The most log entries one call may return.
     *
     * The vendor's own maximum. Asked for explicitly because the documented
     * default is **50** — *"If not set, the latest 50 records are returned"* —
     * and a default that silently truncates is how a reconciliation reports
     * "still unknown" about a message the carrier is holding.
     */
    public const int LOG_LOOKUP_LIMIT = 1000;

    public function logLookupLimit(): int
    {
        return app(DefaultsRegistry::class)->int('sms.infobip.log_lookup_limit');
    }

    /**
     * The media type declared on every link segment.
     *
     * ⚠️ **A DELIBERATE UNDER-CLAIM, NOT A GUESS AT THE FILE'S REAL TYPE.**
     * `MmsOutboundLinkSegment::$contentType` is optional in the vendor's own
     * model, and this application does not read the bytes it is handing over —
     * it is given a URL by L3's composer. Sniffing a remote URL to name its type
     * would be an HTTP call inside a send, and asserting `image/jpeg` for
     * everything would be a claim about somebody else's file that is wrong the
     * first time a PNG arrives. `application/octet-stream` is the honest "we are
     * not telling you"; the carrier fetches the URL and reads the real header.
     */
    private const string MEDIA_CONTENT_TYPE = 'application/octet-stream';

    /**
     * @param  list<string>  $mediaUrls
     */
    public function send(
        string $to,
        string $body,
        ?string $reference = null,
        ?string $from = null,
        array $mediaUrls = [],
    ): SentText {
        // ⚠️ **THE PRESENCE OF MEDIA CHOOSES THE PRODUCT, AND NOTHING ELSE
        // DOES.** No flag, no channel parameter, no per-tenant setting: an
        // `OutboundMessage` either carries media or it does not, and
        // `isMultimedia()` one layer up is the same question asked of the same
        // list. Two ways to reach MMS would be two things to keep in agreement.
        $multimedia = $mediaUrls !== [];

        $url = $this->baseUrl().($multimedia ? '/mms/2/messages' : '/sms/3/messages');

        $handle = $this->mintHandle($multimedia);

        $message = [
            // ⚠️ THE SELECTED NUMBER WINS AND THE CONFIGURED ONE IS THE
            // BOOTSTRAP FALLBACK — see `sender()`. Never the other way round: a
            // configured value that overrode the selection would make a
            // quarantine unenforceable through the one driver that reaches a
            // carrier, and it would look correct in every test that runs on the
            // log driver.
            //
            // ⚠️ **THE SAME NUMBER ON BOTH PRODUCTS, WHICH IS 2193.** MMS is not
            // a different sender and must never be given one: the missed call workflow requires the
            // text-back to come from the number the caller dialled, and a tenant
            // whose picture arrives from a second number reads as somebody else.
            'sender' => $from ?? $this->sender(),
            // ⛔ **`messageId` IS OURS AND IT IS WHAT MAKES AN UNKNOWN OUTCOME
            // ANSWERABLE** (7066, built at 7360). The vendor's own schema:
            // *"The ID that uniquely identifies the message sent. Anything
            // above 200 characters passed in the request will be clipped
            // during processing and **returned in response, reports and
            // logs**"* — `Destination.messageId`, `writeOnly`, raw OpenAPI
            // `https://api.infobip.com/platform/1/openapi/sms`, version
            // `3.222.1`, fetched 2026-08-22. `SMSResponseDetails.messageId`
            // adds *"If not passed, it will be automatically generated"*, which
            // is the behaviour every send on this transport has had until now.
            //
            // ⛔ **THIS IS NOT IDEMPOTENCY AND MUST NEVER BE READ AS IT** —
            // 2545 and 7065 both looked for one and there is none. Supplying an
            // id does not stop a retry sending a second message; it makes a
            // first message that was never acknowledged **findable** for
            // forty-eight hours through {@see self::outcomesFor()}.
            //
            // ⚠️ **THE `$reference` FIELD COULD NOT BE REUSED FOR IT AND SAYS
            // SO TWENTY LINES DOWN**: `webhooks.callbackData` carries the
            // business id home on the delivery receipt, and a caller-supplied
            // message id *"is a different thing"*. These are two fields with
            // two jobs and this method now sends both.
            //
            // ⛔ **THE ONE RESIDUAL RISK, NAMED RATHER THAN DISCOVERED IN
            // PRODUCTION** (7372). {@see self::accepted()} reads
            // `messages.0.messageId` out of the response and that value becomes
            // `outreach_messages.provider_msg_id`, which is the **only** column
            // `DeliveryReceipts` joins an incoming receipt against. Supplying an
            // id changes which value the vendor puts there. The specification
            // covers exactly this — the supplied id is *"returned in response,
            // **reports** and logs"* — so response and report carry the same
            // value either way, and **this application never assumes the two are
            // the same; it reads whatever the response gave it, exactly as it
            // did before.**
            //
            // ⚠️ **SO THE ASSUMPTION IS ONLY THAT THE VENDOR IS CONSISTENT WITH
            // ITSELF**, which is what delivery receipts have always relied on.
            //
            // ⛔ **IF IT IS NOT, THE SYMPTOM IS SILENT AND TOTAL — AND THE
            // SYMPTOM WRITTEN HERE WAS THE WRONG ONE. CORRECTED 2026-08-22
            // (7480, 7481).** This said *"every `outreach_messages` row sits at
            // `Queued` for ever"*, and rows sit at **`Sent`**:
            // `SendSettlement::settle()` moves the row the moment the carrier
            // names the message, before any receipt exists, and `MessageLog`
            // renders that as **"Sent"**. ⛔ **So the thing anybody would look
            // for is not the thing that would happen** — nobody is hunting a
            // stuck queue, and a log of ordinary-looking "Sent" rows is what a
            // total receipt blackout produces. Verified by driving a settlement
            // and reading the row back.
            //
            // ⛔ **AND THE CONSEQUENCE THE OLD SENTENCE STOPPED SHORT OF IS THE
            // ONE THAT MATTERS** (7482). `sending_health_windows.delivered`
            // never moves, `SendingRates::hasEnoughVolume()` therefore answers
            // false for ever, and **the automatic per-tenant complaint trip and
            // 2102's platform-wide halt are both switched off** — the
            // containment `CLAUDE.md` calls a precondition of sending at all
            // (2101, 2102, 2113), gone silently, on a live 10DLC campaign.
            //
            // ✅ **IT IS NO LONGER SILENT.** `DeliveryReceipts` records every
            // receipt it cannot place, and `SendingGuard` and
            // `WatchPlatformComplaintRate` both say out loud when the trip
            // cannot fire on traffic that has already gone out
            // (`SendingRates::trafficWithoutOutcomes()`). ⚠️ **None of that is
            // proof about this vendor** — 7376 is right that every response in
            // the suite is one the harness wrote — and **the first live
            // campaign is still where it is checked**. The fix remains to stop
            // sending this field.
            'destinations' => [['to' => $to, 'messageId' => $handle]],
            'content' => $multimedia
                ? ['messageSegments' => $this->segments($body, $mediaUrls)]
                // ⚠️ `content.text`, verified twice (2202, 2203). `content.body.text`
                // with a `channel` field belongs to the *Messages API*, which is
                // a different product, and writing it here would fail silently.
                : ['text' => $body],
        ];

        if ($reference !== null) {
            // ⚠️ **`webhooks.callbackData`, WHICH IS WHERE INFOBIP PUTS IT AND
            // NOT WHERE IT LOOKS LIKE IT SHOULD GO.** The obvious guesses are
            // `options.callbackData` and `destinations[].messageId`; the first
            // does not exist and the second is a caller-supplied *message id*,
            // which is a different thing that would collide with the carrier's
            // own. Read from `SmsWebhooks` in Infobip's generated client on
            // 2026-08-09 — it carries exactly three fields, `delivery`,
            // `contentType` and `callbackData`, and `SmsDeliveryReport` echoes
            // the last one back.
            $message['webhooks'] = ['callbackData' => $reference];
        }

        try {
            $response = VendorLog::timed(
                'infobip',
                'POST',
                $url,
                fn (): Response => $this->request()->post($url, ['messages' => [$message]]),
            );
        } catch (ConnectionException $e) {
            // ⛔ **THE REASON IS BOUND AND READ.** `} catch (ConnectionException) {`
            // — PHP 8's non-capturing catch — is what made discarding it the
            // path of least effort here, and this site took it from the day it
            // was written. See {@see self::neverLeft()} for what the two
            // outcomes cost and why the allowlist is two codes long.
            $neverLeft = $this->neverLeft($e);

            // ⚠️ **THE VENDOR LOG LEARNS THE DISTINCTION TOO**, because the
            // operator reading it at 2am is asking the job's question: is a
            // duplicate possible? It was `ConnectionException::class` for every
            // outcome, which answers nothing.
            //
            // ⚠️ **BOTH THE LOG AND THE EXCEPTION ARE DERIVED FROM THE SAME
            // PREDICATE RATHER THAN FROM EACH OTHER**, so this file never reads
            // `mayHaveReachedCarrier` and the two cannot disagree about which
            // outcome this was. A lint keeps the flag's readers to the three
            // jobs that decide a retry.
            VendorLog::failure(
                'infobip',
                'POST',
                $url,
                $neverLeft ? self::REASON_NEVER_LEFT : self::REASON_UNKNOWN,
            );

            // ⚠️ **THE HANDLE RIDES ONLY THE ARM THAT MIGHT NEED IT.**
            // `neverLeft()` asserts nothing was submitted, so there is nothing
            // for anybody to ask about and a handle on it would invite the
            // question — see the exception's own docblock for why null is the
            // honest answer on every constructor that does not take one.
            // ⛔ **THIS READ *"SIX OF ITS EIGHT CONSTRUCTORS"* AND WAS ALREADY
            // WRONG BY TWO BEFORE THIS WAVE TOUCHED THE CLASS — CORRECTED
            // 2026-08-28 (11310).** There were **ten**, `transport()` made
            // eleven, and the count had been drifting silently because nothing
            // reads it. ⚠️ **It is restated as a property and not as a new
            // count** (11277): exactly three factories —
            // `unreachable()`, `rejected()` and `unreadable()` — take a
            // `?string $handle`, because they are the three that can name a
            // message the vendor might be holding.
            throw $neverLeft
                ? TextNotDeliverable::neverLeft(self::REASON_NEVER_LEFT)
                : TextNotDeliverable::unreachable(self::REASON_UNKNOWN, $handle);
        }

        if ($response->failed()) {
            throw TextNotDeliverable::rejected($response->status(), $handle);
        }

        return $this->accepted($response, $handle);
    }

    /**
     * Whether this connection failure establishes that nothing left the process.
     *
     * ⛔ **IT WIDENS A SAFE DEFAULT AND NEVER NARROWS ONE, IN EVERY DIRECTION IT
     * CAN FAIL** (7061, and `ZernioGbpClient`'s posture one vendor over).
     * Anything this method cannot positively place in {@see self::NEVER_LEFT}
     * keeps the behaviour this transport had before it existed: a Guzzle upgrade
     * that empties the handler context, a message format that changes, a stream
     * handler with no libcurl code at all, an exception raised by `Http::fake()`
     * with no cause attached — all of them answer `null` from
     * {@see self::curlErrno()}, and `null` is not in the list. **There is no
     * version of this going wrong that texts anybody twice.**
     *
     * ⚠️ **ONE CALL SITE, DELIBERATELY, AND A LINT HOLDS IT.** The alternative
     * is a classification per catch body, and the next catch body added is where
     * the reason gets discarded again — which is precisely what the site above
     * did for as long as it existed.
     *
     * ⚠️ **A PREDICATE RATHER THAN A FACTORY**, so that the `VendorLog` reason
     * and the thrown exception are two consequences of one reading instead of
     * one being derived from the other. Reading the flag back off the exception
     * would put a fourth reader in `app/` and make this file's log line
     * dependent on a property whose whole meaning is *"we do not know"*.
     */
    private function neverLeft(ConnectionException $e): bool
    {
        return in_array($this->curlErrno($e), self::NEVER_LEFT, true);
    }

    /**
     * The libcurl code behind a connection failure, or null if it cannot be read.
     *
     * **Two sources, structured first, and the fallback is the one the tests can
     * reach.** Read from the installed framework rather than from memory
     * (`vendor/laravel/framework/src/Illuminate/Http/Client/PendingRequest.php`,
     * 2026-08-21):
     *
     *   1. `marshalConnectionException()` builds
     *      `new ConnectionException($e->getMessage(), 0, $e)`, so the Guzzle
     *      exception is the **previous** and `ConnectException::getHandlerContext()`
     *      carries `errno` from `CurlFactory::finishError()`'s
     *      `['errno' => $easy->errno, …]`. A number rather than a sentence, and
     *      what production gets.
     *   2. Guzzle's own message is
     *      `sprintf('cURL error %s: %s (%s)', $ctx['errno'], …)`
     *      (`CurlFactory.php`, guzzlehttp/guzzle 7.15.2), so the code is also
     *      the first thing in the string.
     *
     * ⚠️ **THE FALLBACK IS LOAD-BEARING RATHER THAN DEFENSIVE.**
     * `Http::failedConnection()` builds its `ConnectException` with **no handler
     * context at all**, so in the harness the string is the only source there
     * is — deleting the `preg_match` makes every never-left assertion in
     * `tests/Feature/Messaging/SmsTransportOutcomeTest.php` unreachable while
     * the suite stays green on the safe arm.
     *
     * ⚠️ **THE PATTERN IS ANCHORED AT `^` ON PURPOSE.** The same message ends
     * with the request URI, and on this transport that URI is a vendor endpoint
     * rather than anything a stranger supplies — but the anchor costs nothing
     * and the unanchored version is one media URL away from letting a third
     * party's string reach the capture group.
     *
     * ⛔ **IT IS A SECOND COPY OF `ZernioGbpClient::curlErrno()` AND THAT IS A
     * DELIBERATE, RECORDED COST** (7064). That method is `private static` in
     * another lane's live file this wave, and the three lints in
     * `tests/Feature/Architecture/GbpTest.php` fence its neighbourhood in.
     * Consolidating the two into one home is owed and is written down rather
     * than left for a grep to discover.
     */
    private function curlErrno(ConnectionException $e): ?int
    {
        $cause = $e->getPrevious();

        if ($cause instanceof ConnectException) {
            $errno = $cause->getHandlerContext()['errno'] ?? null;

            // Positive rather than merely `int`: `finishError()` retries a
            // rewind rather than rejecting when `errno` is zero, so a zero here
            // is a context that was never filled in, not a success.
            if (is_int($errno) && $errno > 0) {
                return $errno;
            }
        }

        return preg_match('/^cURL error (\d+):/', $e->getMessage(), $matches) === 1
            ? (int) $matches[1]
            : null;
    }

    /**
     * Mint the id this application puts on the wire for one message.
     *
     * ⛔ **IT IS MINTED HERE RATHER THAN HANDED DOWN FROM A CALLER, AND THAT IS
     * A REVERSAL OF WHAT 7066 AND 7192(d) BOTH ASSUMED** (7361). Both records
     * describe the work as widening `App\Contracts\Texter` — *"it widens
     * `Texter` (the 1592 trap)"* — because the obvious shape is a caller
     * supplying an id it can remember. **Three things say the callee should mint
     * it instead, and the last of them is a privacy defect rather than a
     * preference:**
     *
     *   1. **The product is only knowable here.** A handle has to name the
     *      endpoint that answers for it, and this method is the only place that
     *      knows there were two — see {@see self::SMS_HANDLE_PREFIX}.
     *   2. **The only value a caller had to offer is wrong for the job.**
     *      `SendKey` is deterministic across attempts by design, so a `Failed`
     *      attempt followed by a real send would submit the same id twice and
     *      the log answer for the first would be read as the answer for the
     *      second.
     *   3. ⛔ **AND `SendKey` IS A SHA256 OF THE RECIPIENT.**
     *      `CampaignRecipient`'s own docblock: it hashes the recipient
     *      *"precisely so the value can reach a row, a log line and a Horizon
     *      tag without a mobile number reaching any of them."* Putting it on the
     *      wire would hand a third party a **stable per-recipient token**, held
     *      in their log store, identical on every message that person ever
     *      receives from us — the cross-message identifier `29` §2.4 and the
     *      pixel's own no-stable-identifier rule exist to prevent.
     *
     * ⚠️ **SO WHAT GOES ON THE WIRE IS RANDOM AND SAYS NOTHING**: a product
     * prefix and a v4 UUID. It cannot be correlated with any other message, any
     * tenant, or any person, and it is worthless to anybody who is not holding
     * the row that names it.
     *
     * ⚠️ **AND THE INTERFACE DID NOT HAVE TO WIDEN AT ALL.** `Texter::send()`
     * and `PlatformTexter::sendToCustomer()` are unchanged, so the eight
     * anonymous test doubles that implement `Texter` are untouched and 1592's
     * zero-byte run was never in play. That is a saving rather than the reason;
     * points 1 to 3 are the reason.
     */
    private function mintHandle(bool $multimedia): string
    {
        return ($multimedia ? self::MMS_HANDLE_PREFIX : self::SMS_HANDLE_PREFIX)
            .hash('sha256', (string) hrtime(true));
    }

    /**
     * Ask the carrier what became of messages we can no longer account for.
     *
     * ⛔ **THIS IS THE ONLY THING IN THE APPLICATION THAT CAN TURN *"WE DO NOT
     * KNOW"* INTO *"WE ASKED"*, AND IT HAS A FORTY-EIGHT-HOUR WINDOW.**
     * `GET /sms/3/logs` and `GET /mms/2/logs` both say so in the specification:
     * *"The available logs are limited to those generated in the last 48
     * hours, and you can retrieve a maximum of only 1000 logs per call"* (raw
     * OpenAPI `https://api.infobip.com/platform/1/openapi/sms` and
     * `…/openapi/mms`, both `3.222.1`, fetched 2026-08-22). A row that falls out
     * of that window is unanswerable for ever and the reconciler stops asking.
     *
     * ⛔ **IT NEVER THROWS AND NEVER PARTIALLY ANSWERS.** Every way this call
     * can fail — a missing credential, a foreign host, a connection that never
     * opened, a 401, a 429, a 500, a body that will not parse — produces the
     * *same* answer as a handle the vendor has never heard of: **no key**. That
     * is deliberate and it is the safety property, because the caller's rule is
     * that a missing key means *"no answer"* and never *"nothing was sent"*.
     * A method that threw would make an outage look different from an absence
     * at exactly the call site least able to tell them apart.
     *
     * ⚠️ **IT IS A READ OF OUR OWN ACCOUNT AND IS NOT A BILLED MESSAGE**, which
     * is why there is no `credit_ledger` debit under it — the same shape as
     * 4720's finding one vendor over, where metering the unbilled quantity would
     * have produced a permanently-zero rate. What bounds it is the caller's
     * cadence and the chunk size, both stated.
     *
     * ⚠️ **NOTHING ABOUT THE RECIPIENT COMES BACK OUT.** The log entry carries
     * `destination`, `content.text` and `price`; {@see SendLogEntry} takes none
     * of them.
     *
     * @param  list<string>  $handles
     * @return array<string, SendLogEntry>
     */
    public function outcomesFor(array $handles): array
    {
        $wanted = array_values(array_unique($handles));
        $answers = [];

        // ⛔ **THE EMPTY QUESTION IS REFUSED BY THIS LOOP AND NOT BY A GUARD
        // ABOVE IT, AND THE GUARD WAS THERE UNTIL A MUTATION PROVED IT DEAD**
        // (7368, 398's shape inverted). An explicit `if ($wanted === []) return
        // [];` reads as the safety, and `array_chunk([], …)` is `[]` — so the
        // early return could be deleted with every test still green, which
        // means it was never the thing doing the work. **What actually refuses
        // is that a handle has to land in a product bucket before any request
        // is built**, and that is stronger than the guard was: a handle with no
        // recognised prefix is refused too.
        //
        // ⚠️ **WHY IT MATTERS THAT NOTHING EMPTY GOES OUT**: `messageId=` with
        // no value is not the request we mean, and the vendor would answer it
        // with the latest fifty messages on the account — every one of them
        // some other tenant's row.
        foreach ([self::SMS_HANDLE_PREFIX => '/sms/3/logs', self::MMS_HANDLE_PREFIX => '/mms/2/logs'] as $prefix => $path) {
            $forProduct = array_values(array_filter(
                $wanted,
                static fn (string $handle): bool => str_starts_with($handle, $prefix),
            ));

            foreach (array_chunk($forProduct, $this->logLookupChunk()) as $chunk) {
                foreach ($this->logEntries($path, $chunk) as $entry) {
                    $answers[$entry->handle] = $entry;
                }
            }
        }

        return $answers;
    }

    /**
     * One page of the carrier's log, or nothing at all.
     *
     * ⚠️ **THE ANSWER IS FILTERED BACK AGAINST WHAT WAS ASKED**, even though the
     * `messageId` filter should make that impossible. This account is
     * platform-wide and the rows it answers for are tenant-owned and
     * RLS-`FORCE`d: a vendor that widened its own filter would hand this method
     * another tenant's message, and the caller would write it onto the row it
     * happened to be reconciling. The filter costs one `in_array` and removes
     * the whole class.
     *
     * @param  list<string>  $chunk
     * @return list<SendLogEntry>
     */
    private function logEntries(string $path, array $chunk): array
    {
        try {
            $url = $this->baseUrl().$path;

            $response = VendorLog::timed(
                'infobip',
                'GET',
                $url,
                fn (): Response => $this->request()->get($url, [
                    // Comma-separated, which is the vendor's own spelling for
                    // this parameter: *"May contain multiple comma-separated
                    // values."* Laravel would otherwise serialise a PHP array
                    // as `messageId[]=`, which this API does not read.
                    'messageId' => implode(',', $chunk),
                    'limit' => $this->logLookupLimit(),
                ]),
            );
        } catch (TextNotDeliverable|ConnectionException $e) {
            // ⚠️ **A LABEL, NEVER THE EXCEPTION'S MESSAGE.** A connection
            // exception's text carries the full request URI and this one has
            // every handle we asked about in its query string; `VendorLog`'s
            // rule and `TextNotDeliverable`'s class docblock both forbid it.
            VendorLog::failure('infobip', 'GET', $path, $e::class);

            return [];
        }

        if ($response->failed()) {
            VendorLog::failure('infobip', 'GET', $path, 'http_'.$response->status());

            return [];
        }

        $results = $response->json('results');

        if (! is_array($results)) {
            VendorLog::failure('infobip', 'GET', $path, 'results_unreadable');

            return [];
        }

        $entries = [];

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $handle = $result['messageId'] ?? null;

            if (! is_string($handle) || ! in_array($handle, $chunk, true)) {
                continue;
            }

            $group = $result['status']['groupName'] ?? null;
            $sentAt = $result['sentAt'] ?? null;

            $group = is_string($group) && $group !== '' ? mb_strtoupper($group) : null;

            $entries[] = new SendLogEntry(
                handle: $handle,
                // ⛔ **THE HALVES OF `REFUSED_GROUPS`, ASKED SEPARATELY, WHICH
                // IS THE WHOLE OF 7500.** The submission path asks one question
                // of the merged list and is unchanged; this path asks a
                // different one — *did the carrier ever have this message* —
                // and there `REJECTED` and `UNDELIVERABLE` are opposites. The
                // vocabulary is still read in this file only, and what leaves
                // it is a decision rather than a vendor string.
                //
                // ⚠️ **THE ORDER OF THE ARMS IS LOAD-BEARING**: an absent group
                // is answered *before* either list, because `null` is in
                // neither and would otherwise fall through to `Took` — reading
                // a silence as an acceptance, which is the same error as the
                // refusal it used to be read as, pointing the other way.
                verdict: match (true) {
                    $group === null => CarrierVerdict::Unstated,
                    in_array($group, self::DECLINED_GROUPS, true) => CarrierVerdict::Declined,
                    in_array($group, self::UNDELIVERED_GROUPS, true) => CarrierVerdict::Undelivered,
                    default => CarrierVerdict::Took,
                },
                statusGroup: $group,
                sentAt: $this->logTime($sentAt),
            );
        }

        return $entries;
    }

    /**
     * The vendor's `sentAt`, or null if it cannot be read.
     *
     * ⚠️ **A TIME THIS METHOD CANNOT PARSE BECOMES NULL AND NEVER `now()`.** The
     * caller writes `sent_at` from it, `SendCollisionArbiter` measures a
     * marketing touch window against that column, and a substituted `now()`
     * would put a message sent thirty hours ago into today — blocking a send
     * that was allowed, on evidence nobody has.
     */
    private function logTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (InvalidFormatException) {
            return null;
        }
    }

    /**
     * The MMS body, as Infobip's segment list.
     *
     * ⛔ **THERE IS NO `type` FIELD AND THERE NEVER WAS — 4261, FIXED HERE
     * 2026-08-16 (4385–4388).** `MmsAdvancedMessageSegment` is an `anyOf` over
     * five options and **not one of the five carries a `type`**: Text is
     * `{contentId?, text?}`, Link is `{contentId?, contentType?, contentUrl}`,
     * and the other three are Binary, Smil and UploadReference. Read from
     * `https://www.infobip.com/docs/api/channels/mms/send-mms/send-mms-message.md`
     * on 2026-08-12 (4261) and **re-read from the same URL on 2026-08-16 before
     * this change**: `grep -c '"type"'` over the whole reference is `0`, and
     * every documented example writes a bare `{"contentId": …, "text": …}`.
     *
     * ⚠️ **2542 WAS NOT CARELESS — IT READ THE WRONG KIND OF ARTEFACT.**
     * `TEXT LINK SMIL UPLOADED_REFERENCE` are the *generated PHP client's* model
     * discriminators, which an OpenAPI generator synthesises to choose a class
     * when deserialising an `anyOf`. They are not wire fields, and the published
     * request schema is the authority for what goes on the wire.
     * `docs/FAILURE-SHAPES.md`'s *"Verify a vendor string … against the raw
     * artefact"* has a second edge here:
     * a generated client **is** an artefact, and it is still not the protocol.
     *
     * ⚠️ **4261 DELIBERATELY DID NOT FIX IT AND SAID WHY** — this was another
     * lane's file, mid-flight, with its own fixtures asserting the key. That
     * lane has merged, and this lane is the one that makes MMS fire at all
     * (P16's overlay font was missing, so every picture degraded to SMS before
     * today). **An unrecognised field is harmless until the request is real**,
     * and this is the change that makes it real, so the two belong together.
     * The impact was and remains unknown without a live send — some APIs ignore
     * unknown properties and some reject the segment — and removing a field the
     * schema does not have is the safe direction under either.
     *
     * ⚠️ **THE TEXT SEGMENT COMES FIRST AND THE MEDIA FOLLOWS, WHICH IS THE
     * ORDER THE HANDSET RENDERS.** `messageSegments` is a list rather than a
     * set, and a picture above the words is a different message from the words
     * above the picture — `ReviewInviteSender`'s composer puts the opt-out
     * sentence at the end of the text for a compliance reason, and burying that
     * under an image is the same failure one layer down.
     *
     * ⚠️ **WHICH OPTION A SEGMENT IS, IS NOW CARRIED BY ITS KEYS ALONE**, which
     * is what an `anyOf` means: `text` makes it Text, `contentUrl` makes it
     * Link. Nothing else distinguishes them and nothing else needs to.
     *
     * ⚠️ **AN EMPTY BODY STILL PRODUCES NO TEXT SEGMENT.**
     * `MmsOutboundTextSegment::$text` carries `#[Assert\NotBlank]`, so a blank
     * one is refused by the vendor rather than ignored. It is unreachable from
     * here — `OutboundMessage::for()` and `PlatformTexter` both refuse an empty
     * body first — and the branch exists so that the unreachable case is a
     * missing segment rather than a vendor-side rejection nobody can read.
     *
     * @param  list<string>  $mediaUrls
     * @return list<array<string, string>>
     */
    private function segments(string $body, array $mediaUrls): array
    {
        $segments = [];

        if (trim($body) !== '') {
            $segments[] = ['text' => $body];
        }

        foreach ($mediaUrls as $url) {
            $segments[] = [
                'contentUrl' => $url,
                'contentType' => self::MEDIA_CONTENT_TYPE,
            ];
        }

        return $segments;
    }

    /**
     * Read the one message back out of the envelope, refusing anything that is
     * not a message we can account for.
     *
     * ✅ **ONE READER FOR BOTH PRODUCTS, BECAUSE THE VENDOR RETURNS ONE SHAPE.**
     * `sendSmsMessages()` answers `SmsResponse` and `sendMmsMessages()` answers
     * `MessageResponse`; both are `bulkId` plus `messages[]` with `messageId`
     * and `status.{groupName,name}`. Verified in the generated client rather
     * than assumed, because a second parser here would be a second place for the
     * "a 200 is not an acceptance" rule below to be forgotten.
     *
     * ⚠️ **THE PER-MESSAGE STATUS IS CHECKED INSIDE THE 200.** Infobip reports a
     * refused destination as `status.groupName` of `REJECTED` while the HTTP
     * status stays 200 — a blocked number, an unregistered sender, a campaign
     * that has not cleared. Stopping at `$response->failed()` is how every one
     * of those reports as sent, and it is the shape `CLAUDE.md` records as a
     * test passing for the wrong reason, applied to a vendor call.
     *
     * @throws TextNotDeliverable
     */
    private function accepted(Response $response, string $handle): SentText
    {
        $group = $response->json('messages.0.status.groupName');

        if (is_string($group) && in_array(mb_strtoupper($group), self::REFUSED_GROUPS, true)) {
            throw TextNotDeliverable::notAccepted(mb_strtoupper($group));
        }

        $id = $response->json('messages.0.messageId');

        if (! is_string($id) || $id === '') {
            // Refused rather than papered over: this id is the only thing slice
            // 3's delivery receipt can match on, so a send we cannot name is a
            // row that sits Queued forever with no way to find out otherwise.
            //
            // ⛔ **THIS IS THE ARM THE HANDLE WAS BUILT FOR** (7063). The
            // carrier answered 200 and did not refuse the destination, so the
            // message **is** at Infobip and only its name is missing — which
            // is precisely the state {@see self::outcomesFor()} can resolve,
            // because the vendor is holding it under the id we supplied.
            throw TextNotDeliverable::unreadable('message_id_missing', $handle);
        }

        $bulk = $response->json('bulkId');
        $name = $response->json('messages.0.status.name');

        return new SentText(
            providerMessageId: $id,
            bulkId: is_string($bulk) && $bulk !== '' ? $bulk : null,
            vendorStatus: is_string($name) && $name !== '' ? $name : null,
        );
    }

    /**
     * @throws TextNotDeliverable
     */
    private function request(): PendingRequest
    {
        try {
            $key = PlatformCredentials::get('infobip_api_key');
        } catch (RuntimeException $e) {
            // Converted rather than propagated so that every reason this
            // transport cannot send is one exception type at the call site.
            // ⚠️ The vendor's own message is safe to carry here and nothing
            // else is: PlatformCredentials::get() writes it itself and it names
            // the key, never a value.
            throw TextNotDeliverable::notConfigured($e->getMessage());
        }

        return Http::withHeaders(['Authorization' => self::AUTH_PREFIX.' '.$key])
            ->timeout((int) config('services.infobip.timeout', 10))
            ->acceptJson();
    }

    /**
     * Infobip's registrable domain — the only constraint a per-account host can
     * carry.
     *
     * ⚠️ **DELIBERATELY NOT `.api.infobip.com`, AND THE TIGHTER VERSION WOULD
     * HAVE REFUSED PRODUCTION** (1593). The real host is
     * `xxxxx.api-us.infobip.com`: the region is a segment of its own, and both
     * this project's earlier guess (431's `api.infobip.com`) and Infobip's own
     * documentation illustration (`example.infobip.com`) omit it. Anything
     * narrower than the registrable domain is a guess about somebody else's
     * infrastructure, enforced as fact.
     */
    private const string HOST_SUFFIX = '.infobip.com';

    /**
     * @throws TextNotDeliverable
     */
    private function baseUrl(): string
    {
        $base = config('services.infobip.base_url');

        if (! is_string($base) || trim($base) === '') {
            throw TextNotDeliverable::noBaseUrl();
        }

        $base = rtrim(trim($base), '/');

        $this->assertInfobipHost($base);

        return $base;
    }

    /**
     * Refuse to send our API key and a customer's phone number somewhere else.
     *
     * ⚠️ **THIS IS THE ONLY THING GUARDING THIS DESTINATION, AND THE OUTBOUND
     * LINT CANNOT HELP** (1576). Infobip issues a per-account base URL, so there
     * is no host literal in this file for `OutboundTest` to read — the vendor is
     * invisible to every assertion in it, and `INFOBIP_BASE_URL` is exempt from
     * the env-default rule for exactly that reason. What was left was an
     * unconstrained environment variable on the one client that carries both our
     * platform credential and a member of the public's mobile number.
     *
     * A suffix check does not make the host enumerable, and it is not meant to.
     * It bounds the set: an `.env` edit can move us between Infobip's own
     * regions and can no longer move us off Infobip at all.
     *
     * ⚠️ **`parse_url()` FIRST, NEVER `str_ends_with()` ON THE WHOLE STRING** —
     * decision 223's rule, which this codebase has now applied to three
     * different problems. `https://evil.test/?x=.infobip.com` ends with the
     * suffix and is not an Infobip host; so does `https://notinfobip.com`
     * against a bare suffix without the leading dot.
     *
     * ⚠️ **PARSING FIRST CREATES A SECOND REFUSAL AND THEY ARE NOT THE SAME
     * FAILURE** (9120). A value that yields no host has not failed the suffix
     * check — it never reached it. Each branch says so in its own words, and
     * the dataset in `InfobipClientTest` exercises both, because every case in
     * it carried a scheme until 2026-08-24 and the schemeless one is the shape
     * that actually shipped.
     *
     * @throws TextNotDeliverable
     */
    private function assertInfobipHost(string $base): void
    {
        $host = parse_url($base, PHP_URL_HOST);

        // ⛔ **A DISTINCT REFUSAL, NOT THE SUFFIX ONE** (9120). This branch fires
        // when nothing URL-shaped could be read at all — overwhelmingly a value
        // stored without its scheme, which `parse_url()` reads as a *path* and
        // answers with no host. It threw `foreignHost($base)` until 2026-08-24,
        // so the correct production host arrived in an operator's terminal
        // accused of not being an Infobip host. **Do not merge these two
        // branches back into one message**: they have opposite fixes — one adds
        // a scheme, the other changes the destination.
        if (! is_string($host) || $host === '') {
            throw TextNotDeliverable::unparseableBaseUrl($base);
        }

        $host = mb_strtolower($host);

        // The leading dot is load-bearing: without it `notinfobip.com` passes.
        if (! str_ends_with($host, self::HOST_SUFFIX)) {
            throw TextNotDeliverable::foreignHost($host);
        }
    }

    /**
     * The configured sender — **the bootstrap fallback, no longer the primary
     * path.**
     *
     * ⚠️ **SLICE 6 INVERTED THIS AND THE OLD READING IS THE DANGEROUS ONE.**
     * Through slice 5 this was the only answer to *"which number does a message
     * come from"*, because there was no inventory to ask. There is now:
     * `NumberSelector` chooses a `phone_numbers` row and `PlatformTexter` hands
     * it down as `$from`. This method answers only when the table is empty —
     * a fresh install before `sms:register-sender` has run, and a test database
     * that seeds no numbers.
     *
     * **It is not deleted, and it is not a redundancy.** `INFOBIP_SENDER` is the
     * number the 10DLC campaign is registered against; deleting the fallback
     * would mean an environment whose inventory has not been seeded sends from
     * nothing at all, and a message from an unregistered sender is not refused
     * by the carrier — it is *filtered*, which looks exactly like a delivery
     * that never happened. `config/services.php` makes that argument at the key
     * itself and is why the key has no default.
     *
     * ⛔ **"THE NUMBER THE CAMPAIGN IS REGISTERED AGAINST" IS A FILING AND NOT
     * AN APPROVAL, AND THIS PARAGRAPH READ AS THOUGH IT WERE ONE — CORRECTED
     * 2026-08-29** (12334). **Nothing in this application reads a campaign's
     * status.** `BrandRegistration`'s only writer is `sms:brand-registration`,
     * which records what a person read off a vendor console; there is no vendor
     * call and nothing polls. So this sentence can state which number was
     * *filed*, and it cannot state that the filing was accepted.
     *
     * ⛔ **ON THE DAY THIS CORRECTION WAS WRITTEN THE FILING STOOD REJECTED,
     * CODE 9999** (12279) — the opt-in named the tenant and the campaign named
     * us. **The argument above is untouched by that**: it is an argument for
     * keeping the fallback, never a claim that sending from it is safe.
     * ⚠️ **What the two readings change is the class docblock's last sentence.**
     * A message accepted and then filtered because the sender is not the
     * registered number is invisible here either way — under an approved
     * registration that invisibility covers an unlikely event, and under a
     * rejected one it covers **the expected outcome of every send**. Nothing in
     * this repository can tell you which of those it is today.
     *
     * ⚠️ **AN EMPTY TABLE IS THE ONLY CASE IT COVERS.** A table with rows and
     * nothing sendable does not reach this driver at all — `PlatformTexter`
     * refuses first, silently, because a quarantine that fell back to the
     * configured number would be no quarantine.
     *
     * @throws TextNotDeliverable
     */
    private function sender(): string
    {
        $sender = config('services.infobip.sender');

        if (! is_string($sender) || trim($sender) === '') {
            throw TextNotDeliverable::noSender();
        }

        return trim($sender);
    }
}

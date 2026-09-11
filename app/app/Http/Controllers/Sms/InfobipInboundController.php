<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sms;

use App\Enums\PlatformHealthSignal;
use App\Http\Controllers\Controller;
use App\Services\Ops\PlatformHealth;
use App\Services\Sms\InboundMediaPayload;
use App\Services\Sms\InboundMessages;
use App\Services\Sms\InfobipWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * `POST /webhooks/infobip/inbound` — where a customer's STOP arrives.
 *
 * ⚠️ **THIS IS THE MOST CONSEQUENTIAL UNAUTHENTICATED ENDPOINT IN THE
 * APPLICATION, AND THE TWO WAYS IT FAILS ARE OPPOSITE.** Accept a forgery and
 * anybody on the internet can suppress any phone number, or lift somebody else's
 * refusal and resume texting them. Refuse a genuine delivery and a customer's
 * STOP is discarded — after which every later send looks perfectly permitted and
 * the failure surfaces as a complaint. The first is a vandalism problem; the
 * second is the one with statutory damages, and it is why the carrier's retries
 * are treated as a feature below rather than as noise.
 *
 * ## Why this is thin, and stays thin
 *
 * Verification is {@see InfobipWebhookVerifier}'s and everything else is
 * {@see InboundMessages}'. `StripeWebhookController` established the shape, and
 * what lives here is the HTTP surface — every branch of which is a decision
 * about **whether Infobip should try again**:
 *
 *   401  the signature did not verify, or none is configured. Retrying will not
 *        fix either, and the request may not be from Infobip at all.
 *   422  the payload was not a shape we can read. Same reasoning.
 *   500  our fault — a database error. The carrier retries, which is wanted.
 *   200  handled, or already handled. Both are finished.
 *
 * ⚠️ **A REPLAY IS A 200, NOT A 409.** The carrier retried because it was not
 * sure we got the first one; telling it the message is a duplicate by way of an
 * error status invites it to keep trying.
 *
 * ⚠️ **NOTHING FROM THE REQUEST REACHES THE RESPONSE BODY.** A caller who is not
 * Infobip must learn nothing from the difference between a bad signature, an
 * unconfigured secret and a malformed payload beyond the status itself — in
 * particular whether this endpoint has a signing key yet.
 */
final class InfobipInboundController extends Controller
{
    public function __invoke(
        Request $request,
        InfobipWebhookVerifier $verifier,
        InboundMessages $inbound,
        PlatformHealth $health,
    ): JsonResponse {
        // ⚠️ BEFORE THE BODY IS READ AS ANYTHING BUT BYTES. The signature covers
        // the exact bytes Infobip sent — their documentation is explicit that
        // any reformatting invalidates it — so nothing may parse this request
        // until it has been shown to be genuine.
        if (! $verifier->verify($request)) {
            $sigHeader = config('services.infobip.signature_header');
            $possible = array_filter(['X-Hub-Signature', 'X-Signature', $sigHeader, 'X-Ib-Exchange-Req-Signature', 'X-Ib-Exchange-Req-Timestamp']);
            $present = [];
            foreach ($possible as $h) {
                if ($request->hasHeader($h)) {
                    $present[] = (string) $h;
                }
            }
            $present = array_values(array_unique($present));

            $sigValue = is_string($sigHeader) ? $request->header($sigHeader, '') : '';
            $sigValue = is_string($sigValue) ? $sigValue : '';

            $results = $request->input('results');
            $messageIds = [];
            if (is_array($results)) {
                foreach (array_slice($results, 0, 5) as $m) {
                    if (is_array($m) && isset($m['messageId']) && is_string($m['messageId'])) {
                        $messageIds[] = $m['messageId'];
                    }
                }
            }

            Log::warning('Infobip inbound refused: signature did not verify', [
                'signature_headers_present' => $present,
                'signature_len' => strlen($sigValue),
                'signature_prefix' => $sigValue !== '' ? substr($sigValue, 0, 7) : null,
                'body_sha256' => hash('sha256', $request->getContent()),
                'content_length' => $request->header('Content-Length'),
                'message_ids' => $messageIds,
                'user_agent' => $request->header('User-Agent'),
            ]);

            // ⚠️ **COUNTED (T176 P23), AND THIS IS THE ENDPOINT WHERE THE
            // SILENCE IS A COMPLIANCE PROBLEM.** An unconfigured signing key
            // refuses every genuine delivery, which means every STOP arriving
            // here is dropped — the opt-out is never recorded and the sender
            // keeps sending. `bootstrap/app.php` already calls that failure "an
            // endpoint that does nothing"; the count is what makes somebody
            // notice it doing nothing.
            $health->recordFailure(PlatformHealthSignal::WebhookSignature, 'infobip_inbound');

            return response()->json(['error' => 'unverified'], 401);
        }

        $messages = $request->input('results');

        if (! is_array($messages)) {
            // ⚠️ INFOBIP DELIVERS A BATCH, ALWAYS — `SmsInboundMessageResult`
            // wraps `results[]` even for a single message. A handler written
            // against a bare single-message body would work against a
            // hand-rolled fixture and drop every real delivery.
            return response()->json(['error' => 'unreadable payload'], 422);
        }

        $handled = 0;

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $id = $message['messageId'] ?? null;
            $from = $message['from'] ?? $message['sender'] ?? null;

            // ⚠️ A MESSAGE WITH NO SENDER OR NO ID IS SKIPPED RATHER THAN
            // FAILING THE BATCH, and the choice is deliberate: one unreadable
            // entry must not cost us the STOP sitting beside it in the same
            // delivery. What it must not do is pass silently — the count below
            // is what tells the carrier, and our own logs, that fewer messages
            // were handled than arrived.
            if (! is_string($id) || $id === '' || ! is_string($from) || $from === '') {
                continue;
            }

            // ⚠️ **`cleanText`/`text` ARE THE *SMS* RENDERER'S KEYS AND AN MMS
            // CARRIES NEITHER** (4259, verified 2026-08-16). Infobip forwards
            // inbound SMS as `MO_JSON_2` and inbound MMS as `MO_MMS_2`, and the
            // second has no top-level body at all — its words are a `message[]`
            // segment with a `value`. So this read alone answered null on every
            // MMS, `InboundKeyword::parse(null)` answered `None`, and **a STOP
            // sent as an MMS was recorded and never honoured**: the suppression
            // was never written and every later send looked permitted.
            //
            // ⚠️ **THE SMS KEYS STILL WIN WHEN THEY ARE THERE.** `cleanText` is
            // the carrier's own keyword-stripped body and is the better input;
            // the fallback is reached only when neither key exists, which is
            // exactly the MMS envelope. (The MO subscription shape is tested
            // below as another fallback. The same fallback in InfobipDeliveryController
            // is NOT in scope here.)
            $text = $message['cleanText'] ?? $message['text'] ?? null;
            if (! is_string($text) && isset($message['content'][0]) && is_array($message['content'][0]) && (! isset($message['content'][0]['type']) || $message['content'][0]['type'] === 'TEXT')) {
                $text = $message['content'][0]['cleanText'] ?? $message['content'][0]['text'] ?? null;
            }
            $text = is_string($text) ? $text : InboundMediaPayload::text($message);

            $receivedAt = $message['receivedAt'] ?? null;

            // ⚠️ **`to` IS WHICH OF *OUR* NUMBERS THE CARRIER DELIVERED THIS TO,
            // AND UNTIL SLICE 6 THIS CONTROLLER NEVER READ IT AT ALL.** Infobip
            // carries it on every inbound result and it is the only thing in the
            // payload that can attribute a STOP to a sending number. Recorded
            // rather than consumed: the reader is phase 2, and a reader shipped
            // over a column nothing writes renders an empty history and reports
            // it as a clean one.
            //
            // ⚠️ **AND IT IS NOT A TENANT.** The number this platform sends from
            // is the shared Lane A pool number; resolving it to a business and
            // narrowing the carrier STOP to that tenant is refused at the write
            // site (1582), not weighed here.
            $to = $message['to'] ?? $message['destination'] ?? null;

            // ⚠️ **THE CARRIER'S OWN PART COUNT, WHICH IS THE ONLY HONEST SOURCE
            // FOR IT** (4923). `SmsMoReport.smsCount` — *"The number of parts
            // the message content was split into"* — verified against Infobip's
            // own schema at
            // `https://www.infobip.com/docs/api/channels/sms/inbound-sms/receive-inbound-sms-messages.md`,
            // read 2026-08-18. ⛔ **It is optional in that schema**, and a
            // missing one stays null rather than becoming 1: the cost book's
            // `segments` column is nullable precisely so *"the carrier did not
            // say"* is sayable, and inventing a 1 would record a claim the
            // vendor never made. **The MMS renderer carries no such field at
            // all** (4259), which is why null is the ordinary answer there.
            $smsCount = $message['smsCount'] ?? null;

            $inbound->handle(
                providerMessageId: $id,
                from: $from,
                text: $text,
                receivedAt: is_string($receivedAt) ? $receivedAt : null,
                // A missing or malformed `to` is null rather than a skipped
                // entry — the sender and the keyword are what honour a STOP, and
                // refusing the whole message over a reporting column would trade
                // a legal obligation for a statistic.
                toNumber: is_string($to) && $to !== '' ? $to : null,
                // ✅ **THE MEDIA REFERENCES, AND THE SHAPE IS NOW VERIFIED**
                // (4256, 2026-08-16). 4167 hedged on `media[]` and
                // `content.messageSegments[]` and warned that if both were wrong
                // nothing would break and nothing would be captured. **Both were
                // wrong.** The documented key is `message[]`, quoted in
                // {@see InboundMediaPayload} against Infobip's own schema, and
                // an entry this application cannot read now says so in the log
                // instead of returning the same silence as an ordinary SMS.
                //
                // A static call rather than an injected service: it is a pure
                // read of one array, with no state, no vendor and nothing to
                // fake. ⚠️ Unreadable media is an empty list and never a skipped
                // entry, on the same reasoning as `to` above — refusing a whole
                // message over an attachment would trade a legal obligation for
                // a photograph.
                media: InboundMediaPayload::parse($message),
                // ⚠️ **`is_int` RATHER THAN A CAST.** JSON gives an int here and
                // anything else — a string, a float, null — is a payload we do
                // not recognise, which must read as "not stated" rather than be
                // coerced into a number the vendor did not send.
                segments: is_int($smsCount) ? $smsCount : null,
            );

            $handled++;
        }

        return response()->json(['handled' => $handled]);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Enums\InboundKeyword;
use Illuminate\Support\Facades\Log;

/**
 * One entry of an Infobip inbound MMS batch, read against Infobip's own schema.
 *
 * ✅ **THE SHAPE IS NOW VERIFIED AGAINST A RAW ARTEFACT, AND BOTH SPELLINGS 4167
 * HEDGED ON WERE WRONG** (4256). Read 2026-08-16 from Infobip's published
 * webhook reference, fetched as raw markdown rather than as a rendered page or a
 * summary of one:
 *
 *   https://www.infobip.com/docs/api/channels/mms/receive-mms/receive-inbound-mms-messages.md
 *
 * The page names itself: *"The default format or renderer for the inbound MMS
 * messages is documented in this section. It is referred to as `MO_MMS_2` when
 * configuring inbound settings via HTTP forward actions, and as
 * `inbound-message.mms.v1.json` when configuring via Subscriptions. Both names
 * refer to the same payload."*
 *
 * ## The documented entry, quoted
 *
 * ```json
 * {
 *   "results": [
 *     {
 *       "from": "41793026727",
 *       "to": "25256",
 *       "receivedAt": "2016-10-06T09:28:39.220+0000",
 *       "messageId": "817790313235066447",
 *       "callbackData": "Some custom data",
 *       "userAgent": "iPhone_12_Pro_Max_A2342",
 *       "message": [
 *         {
 *           "contentType": "image/jpeg",
 *           "url": "https://examplelink.com/123456"
 *         },
 *         {
 *           "contentType": "text/plain",
 *           "value": "This is message text"
 *         }
 *       ],
 *       "price": { "pricePerMessage": 0, "currency": "EUR" }
 *     }
 *   ],
 *   "messageCount": 1,
 *   "pendingMessageCount": 0
 * }
 * ```
 *
 * ⛔ **THE KEY IS `message[]`. IT IS NOT `media[]` AND IT IS NOT
 * `content.messageSegments[]`** — 4167 named exactly these two and warned that
 * *"if both are wrong, nothing throws and `inbound_media` stays empty on a green
 * suite"*. Both were wrong, and the suite was green. This is `CLAUDE.md`'s
 * fifth recorded instance of *verify a vendor string against the raw artefact*
 * (255, 277, 684, 1349), and the first one that was predicted in writing before
 * it was found.
 *
 * ⚠️ **`content.messageSegments[]` WAS NOT A BAD GUESS — IT IS A REAL SHAPE
 * POINTING THE OTHER WAY.** 2542 read it correctly out of the generated client
 * for **outbound** MMS. The inference that failed was *"one product uses one
 * content model"*, which 4167 itself flagged as *"likely and not proof"*. The
 * lesson is the narrower one: a verified shape in the other direction is
 * evidence about that direction only.
 *
 * ## How a picture is told from the words beside it
 *
 * The schema is an `anyOf` over two segment types, **discriminated by which
 * field is present rather than by any `type` key** — there is no `type`
 * discriminator on the inbound side at all:
 *
 *   - `MmsMoLinkSegment` — *"**contentType** (string, optional) — Content type
 *     of the message segment `image/jpeg`. **url** (string, optional) — URL from
 *     which content can be downloaded."*
 *   - `MmsMoTextSegment` — *"**contentType** (string, optional) — Content type
 *     of the message segment `text/plain`. **value** (string, optional) —
 *     Message text."*
 *
 * ⚠️ **SO TEXT AND MEDIA DO COEXIST IN ONE ENTRY**, which is what the documented
 * example shows, and {@see self::text()} exists because of it.
 *
 * ⚠️ **`contentType` IS OPTIONAL ON BOTH**, so it may not be used as the
 * discriminator. `url` is. A link segment's declared type is deliberately not
 * carried onward — {@see InboundMediaPart} argues why, and that argument is
 * untouched by this verification: the type this platform records is the one
 * {@see InboundMediaFetcher} *observed*, header and magic bytes together.
 *
 * ## The other product, which is a different shape and is not this one
 *
 * ⚠️ **THE PULL API IS NOT THE WEBHOOK.** `GET` *Get inbound MMS messages*
 * (https://www.infobip.com/docs/api/channels/mms/receive-mms/get-inbound-mms-messages.md,
 * read the same day) uses the same `message[]` key with a **different part
 * schema** — `MmsMoMessagePart`: *"**origin**, **contentType**, **contentId**,
 * **value**"* — and **no `url` field at all**. Nothing in this application polls
 * it, and if anything ever does it needs its own reader rather than this one.
 *
 * ## What is still not settled, and cannot be settled from the documentation
 *
 * ⛔ **THE MEDIA HOSTNAME IS STILL UNKNOWN AND 4168's EMPTY ALLOWLIST STANDS**
 * (4258). Every example URL on the page is the placeholder
 * `https://examplelink.com/123456`; the field's whole description is *"URL from
 * which content can be downloaded"*. Infobip publishes no download endpoint for
 * inbound MMS content — the *Receive MMS* section contains exactly two pages,
 * neither of them a content fetch — so the host is not derivable from the API
 * surface either. `config/services.php` records what an operator must read off a
 * real delivery.
 */
final class InboundMediaPayload
{
    /**
     * The vendor's key for the parts of a received MMS.
     *
     * ⚠️ **A CONSTANT SO THE LOUD CHECK AND THE PARSE CANNOT DISAGREE.** Both
     * {@see self::parse()} and {@see self::describeIfUnreadable()} ask about the
     * same key, and a second spelling of it is how one would go on reading a
     * payload the other had already called unreadable.
     */
    private const string PARTS_KEY = 'message';

    /**
     * Every media reference in one `results[]` entry, in payload order.
     *
     * ⚠️ **DUPLICATES ARE REMOVED**, so a payload naming one file twice is one
     * picture. The return position is the ordinal, which is half the storage
     * path and half the idempotency key.
     *
     * ⚠️ **AND IT REPORTS A SHAPE IT CANNOT READ BEFORE IT RETURNS NOTHING** —
     * see {@see self::describeIfUnreadable()}. That is the whole of 4257: an
     * empty return is the correct answer for an ordinary SMS and the symptom of
     * a renderer change, and until now those two were the same silence.
     *
     * @param  array<mixed>  $message  One entry of the carrier's `results[]`.
     * @return list<InboundMediaPart>
     */
    public static function parse(array $message): array
    {
        self::reportIfUnreadable($message);

        $urls = [];

        foreach (self::parts($message) as $part) {
            $url = $part['url'] ?? null;

            if (is_string($url) && $url !== '') {
                $urls[] = $url;
            }
        }

        return array_values(array_map(
            fn (string $url): InboundMediaPart => new InboundMediaPart($url),
            array_unique($urls),
        ));
    }

    /**
     * The words that arrived beside the pictures, or null when there were none.
     *
     * ⛔ **THIS EXISTS BECAUSE THE MMS RENDERER HAS NO `text` KEY, AND THAT IS A
     * COMPLIANCE FACT RATHER THAN A CONVENIENCE** (4259). The inbound **SMS**
     * webhook is a different renderer — `MO_JSON_2` /
     * `inbound-message.sms.v1.json` — and carries `text`, `cleanText` and
     * `keyword` at the top of the entry
     * (https://www.infobip.com/docs/api/channels/sms/inbound-sms/receive-inbound-sms-messages.md).
     * **`MO_MMS_2` carries none of the three.** Its text is a segment with a
     * `value`, sitting in the same array as the pictures.
     *
     * So before this method existed, `InfobipInboundController` read
     * `cleanText ?? text` on an MMS, got null, and
     * {@see InboundKeyword::parse()} answered `None`: **a STOP sent as
     * an MMS was recorded and never honoured.** Not a hypothetical — a subject
     * line, an attachment or a group thread is enough to make a handset send MMS
     * instead of SMS, and the message reads `STOP` either way. `InboundKeyword`'s
     * own docblock names the cost: *"a refusal that is not recognised is not a
     * refusal that errors; it is a refusal that is discarded, after which every
     * later send looks perfectly permitted."*
     *
     * ⚠️ **A SEGMENT COUNTS AS TEXT ONLY WHEN ITS TYPE SAYS SO, OR SAYS
     * NOTHING.** `contentType` is optional in the schema, so absence cannot
     * disqualify a `value`; but a segment that positively declares itself
     * `application/smil` is a layout document, and feeding a SMIL body to the
     * keyword parser is how a presentation file gets read as a customer's
     * instruction.
     *
     * ⚠️ **PARTS ARE JOINED WITH A NEWLINE, AND THAT IS THE CONSERVATIVE
     * DIRECTION HERE.** `InboundKeyword::parse()` requires the **whole** message
     * to be the keyword, so joining can only ever turn a would-be keyword into
     * `None` — never the reverse. A single text part, which is what a real MMS
     * carries, is passed through exactly as it arrived.
     *
     * @param  array<mixed>  $message  One entry of the carrier's `results[]`.
     */
    public static function text(array $message): ?string
    {
        $values = [];

        foreach (self::parts($message) as $part) {
            $value = $part['value'] ?? null;

            if (is_string($value) && $value !== '' && self::declaresText($part)) {
                $values[] = $value;
            }
        }

        return $values === [] ? null : implode("\n", $values);
    }

    /**
     * Say so, once, when this entry is a shape this application cannot read.
     *
     * ⛔ **THIS IS THE ANSWER TO 4167's OWN TELL, AND THE TELL WAS THE PROBLEM.**
     * That decision recorded the failure honestly — *"the tell in production is
     * an account receiving MMS with no rows"* — but an absence is not a tell.
     * Nobody watches for rows that are not written; the account that receives no
     * pictures looks exactly like the account whose customers send none, and the
     * suite is green under both. **An empty parse must be able to say which of
     * the two it was**, and that is the only thing this method does.
     *
     * Two shapes are reported, and nothing else is:
     *
     *   1. **`unreadable_entry`** — the entry has no `message[]` *and* neither of
     *      the SMS renderer's `text`/`cleanText` keys. This is 4167's exact
     *      failure generalised: the renderer changed, or the number was
     *      configured with one we have never seen, and every parse from here on
     *      returns nothing forever.
     *   2. **`unreadable_part`** — `message[]` is there and a segment in it is
     *      neither a link (`url`) nor a text segment (`value`). One segment we
     *      cannot read, sitting beside ones we can, is the first sign of a schema
     *      moving under us and is the cheapest possible moment to notice.
     *
     * ⚠️ **NEITHER FIRES ON A HEALTHY MESSAGE, WHICH IS THE POINT.** An ordinary
     * SMS has `text` and no `message[]`: branch 1 is satisfied by the `text` key
     * and branch 2 by the absence of parts, so the overwhelmingly common case is
     * silent. A lint tuned until it stops crying wolf is one tuned until it
     * catches nothing (511) — this one is arranged so it has nothing to cry about
     * until something is genuinely wrong.
     *
     * ⛔ **KEY NAMES ONLY. NEVER A VALUE, AND NEVER THE URL.** The diagnostic
     * question is *"what shape did the vendor send"*, and key names answer it
     * completely. The values on this path are a member of the public's words, a
     * member of the public's mobile number and an address pointing at their
     * photograph — {@see VendorLog}'s allowlist reasoning, applied to an inbound
     * body: build the context from what is safe rather than by redacting what is
     * not. Sorted so the line is stable enough to group on.
     *
     * ⚠️ **A LOG LINE RATHER THAN A FOURTH `PlatformHealthSignal`, DELIBERATELY,
     * AND THE FOLLOW-UP IS WRITTEN DOWN RATHER THAN IMPLIED** (4257). A counted
     * signal wants an alert to read it, an `OperatorAlertKind` to raise and a
     * registry threshold to compare against; a signal added without those is a
     * counter nobody looks at, which is `sending_health_windows`' failure exactly
     * (2496–2499) and would be a worse outcome than this line, not a better one.
     *
     * @param  array<mixed>  $message
     */
    private static function reportIfUnreadable(array $message): void
    {
        $shape = self::describeIfUnreadable($message);

        if ($shape === null) {
            return;
        }

        Log::warning('An inbound message arrived in a shape this application cannot read.', [
            'shape' => $shape,
            'entry_keys' => self::keyNames($message),
            'actor' => 'carrier:infobip',
        ]);
    }

    /**
     * Which unreadable shape this is, or null when the entry is one we handle.
     *
     * ⚠️ **SPLIT OUT SO THE DECISION IS TESTABLE WITHOUT A LOG FAKE**, and so
     * the two branches can be driven independently. The logging above is one
     * line; this is the judgement.
     *
     * @param  array<mixed>  $message
     */
    private static function describeIfUnreadable(array $message): ?string
    {
        $parts = $message[self::PARTS_KEY] ?? null;

        if (! is_array($parts)) {
            // The SMS renderer's own keys. Present-but-null still counts: an SMS
            // with an empty body is an ordinary thing and is not a mystery.
            $isSmsEnvelope = array_key_exists('text', $message)
                || array_key_exists('cleanText', $message);

            return $isSmsEnvelope ? null : 'unreadable_entry';
        }

        foreach ($parts as $part) {
            if (! is_array($part)) {
                return 'unreadable_part';
            }

            $isLink = is_string($part['url'] ?? null) && $part['url'] !== '';
            $isText = is_string($part['value'] ?? null);

            if (! $isLink && ! $isText) {
                return 'unreadable_part';
            }
        }

        return null;
    }

    /**
     * The entry's `message[]`, as arrays, or nothing.
     *
     * @param  array<mixed>  $message
     * @return list<array<mixed>>
     */
    private static function parts(array $message): array
    {
        $parts = $message[self::PARTS_KEY] ?? null;

        if (! is_array($parts)) {
            return [];
        }

        return array_values(array_filter($parts, is_array(...)));
    }

    /**
     * Whether a segment's declared type permits reading its `value` as words.
     *
     * @param  array<mixed>  $part
     */
    private static function declaresText(array $part): bool
    {
        $type = $part['contentType'] ?? null;

        if (! is_string($type) || trim($type) === '') {
            // Optional in the schema, so silence cannot disqualify a `value`.
            return true;
        }

        // `text/plain; charset=utf-8` is the spelling the pull API's own example
        // uses, so the parameters are stripped before the comparison.
        return str_starts_with(mb_strtolower(trim(explode(';', $type, 2)[0])), 'text/');
    }

    /**
     * The sorted key names of an array, and never a value from it.
     *
     * @param  array<mixed>  $value
     * @return list<string>
     */
    private static function keyNames(array $value): array
    {
        // `sort()` reindexes in place, so this is already a list.
        $keys = array_map(strval(...), array_keys($value));
        sort($keys);

        return $keys;
    }
}

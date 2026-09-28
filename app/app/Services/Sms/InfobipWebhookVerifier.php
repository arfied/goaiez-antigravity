<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Contracts\VerifiesWebhookSenders;
use App\Services\Config\DefaultsRegistry;
use App\Support\PlatformCredentials;
use App\Support\WebhookMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Whether an inbound webhook actually came from Infobip.
 *
 * ⚠️ **READ INFOBIP'S LIVE SUBSCRIPTION DOCUMENTATION ON 2026-08-09 RATHER THAN
 * ASSUMING THIS WORKS LIKE STRIPE'S, AND THREE THINGS CAME BACK THAT A
 * FROM-MEMORY IMPLEMENTATION GETS WRONG.** All three are quoted, because each
 * one fails silently:
 *
 *   1. **There is no fixed header name.** Infobip's own note: *"The exact
 *      signing header name depends on your configuration. Verify the header name
 *      and signing behavior in your Infobip account settings."* Stripe has
 *      `Stripe-Signature` and Twilio has `X-Twilio-Signature`; **Infobip has
 *      whatever the notification profile was configured with.** So the header
 *      name is configuration here (`INFOBIP_WEBHOOK_SIGNATURE_HEADER`) and not a
 *      constant, and a hardcoded guess would mean `$request->header()` returning
 *      null on every genuine delivery — which, with the wrong fail direction,
 *      is an endpoint that rejects Infobip and accepts everybody else.
 *   2. **The hash is over the raw body, byte for byte.** *"Calculate HMAC-SHA256
 *      of the raw request body using your signing key. Use the entire JSON body
 *      exactly as received. Do not parse, reformat, strip whitespace, or remove
 *      newline characters before computing the hash. Any modification will cause
 *      the signature to not match."* `$request->getContent()`, never
 *      `json_encode($request->all())` — the same line `StripeWebhookController`
 *      already carries.
 *   3. **HMAC is opt-in per notification profile**, alongside Basic auth, OAuth
 *      2.0 and mTLS. It is *not on by default*. So a webhook can arrive
 *      perfectly unsigned from a correctly configured Infobip account, and an
 *      endpoint that verified "if a signature is present" would accept every
 *      forgery ever sent to it while passing all of its own tests.
 *
 * ⚠️ **WHICH IS WHY THIS FAILS CLOSED ON A MISSING SECRET, AND WHY THAT IS THE
 * WHOLE DESIGN RATHER THAN DEFENSIVENESS.** No secret configured means every
 * request is refused — including real ones. The alternative, skipping
 * verification when unconfigured, is the permissive branch applied exactly where
 * the strict rule was meant to bind, and it would report as working: STOP
 * messages would flow, tests would pass, and the endpoint that can suppress any
 * phone number on the platform would be open to the internet. `CLAUDE.md`
 * records this shape three times and decision 1568 records the most recent one;
 * the symptom of getting it right is an endpoint that does nothing until it is
 * configured, which is a far better failure than the other.
 *
 * ⚠️ **`hash_equals`, NEVER `===`.** A byte-by-byte comparison that returns
 * early leaks the correct prefix through timing, and a signature is guessable
 * one byte at a time against an endpoint that can be retried freely.
 *
 * ## ⛔ A FOURTH FACT, READ 2026-08-16 WHILE BUILDING THE VOICE ENDPOINT
 *
 * **Infobip publishes a second, differently-constructed HMAC scheme**, and this
 * verifier only knew the first. On the Subscriptions/CPaaS X webhook family the
 * signature is over the **timestamp concatenated with the body**, not over the
 * body alone, and the headers are named:
 *
 *   - `X-Ib-Exchange-Req-Signature` — the hexadecimal signature
 *   - `X-Ib-Exchange-Req-Timestamp` — the value prepended to the body
 *   - `X-Ib-Exchange-Req-AccountKey`
 *
 * Infobip's own worked example: for timestamp `1667951050990` and body
 * `{"email":"…"}` the base string is `1667951050990{"email":"…"}` — *"concatenate
 * the timestamp and the request body with no spaces nor separators between
 * them"*, signed `HmacSHA256` with the app's signing secret.
 *
 *   https://www.infobip.com/docs/cpaas-x/subscriptions-management/create-manage-subscriptions
 *   https://www.infobip.com/docs/subscriptions/understanding-subscriptions
 *
 * ⚠️ **WHICH SCHEME A GIVEN NOTIFICATION PROFILE USES IS AN ACCOUNT SETTING, AND
 * THIS CLASS'S FIRST FACT ALREADY SAID SO** — *"the exact signing header name
 * depends on your configuration"*. So both constructions are supported and both
 * are compared in constant time. **This is not a widening of what is accepted:**
 * an attacker must still produce a signature under the shared secret, and a
 * forger who cannot do that for one construction cannot do it for the other.
 * What it prevents is the *other* failure — an endpoint that refuses every
 * genuine delivery because Infobip signed it the documented way this
 * implementation had not read yet, which on the inbound path means **every STOP
 * silently dropped**.
 *
 * ⛔ **AND THE TIMESTAMPED FORM IS AGE-CHECKED, WHICH THE BODY-ONLY FORM CANNOT
 * BE.** A body-only signature is replayable for ever by anyone who captured one
 * delivery; the timestamped one carries the material to refuse a stale replay,
 * so it is refused outside {@see self::MAX_SIGNATURE_AGE_SECONDS}. ⚠️ **The
 * check only applies when the timestamp header is present**, or a body-only
 * profile would start being rejected by a change meant to accept more.
 *
 * ## ⛔ THE SELECTION IS EXCLUSIVE, AND IT WAS A FALL-THROUGH UNTIL 4507
 *
 * `verify()` used to accept if **either** construction validated. That is not
 * the same thing as supporting both, and the difference is a replay: hold one
 * genuine exchange-signed delivery, move its signature into the body-only
 * header, post the raw body `T . body`, and the body-only branch hashes exactly
 * the base string the vendor signed — `hash_equals` succeeds, and because the
 * exchange headers are gone {@see self::timestampIsFresh()} is never reached.
 * **The five-minute window was therefore load-bearing on an accident of the
 * payload format** (`T` must be `ctype_digit`, so `T . body` is not JSON, so the
 * controllers fall out on a missing field) rather than on this class. Now: if
 * either exchange header is present, only the exchange construction is tried.
 *
 * ⚠️ **AND THAT `if` ALONE DOES NOT CLOSE THE REPLAY, WHICH IS WHY THE SCHEME
 * CAN BE PINNED** (4508). An attacker strips the exchange headers rather than
 * keeping them, so the exclusive branch is never entered. The only thing that
 * separates the two domains is knowing which one this account uses, so
 * `INFOBIP_WEBHOOK_SIGNATURE_SCHEME` may be set to `body` or `exchange` and the
 * other construction is then refused outright. It defaults to `auto` — both, as
 * before — because failing closed on an unknown scheme would refuse every
 * genuine delivery, which is the failure this verifier already ranks worst.
 * **Pinning it is a step in the activation runbook, not a preference.**
 */
final class InfobipWebhookVerifier implements VerifiesWebhookSenders
{
    /**
     * The signing key this verifier judges every Infobip delivery with.
     *
     * ⚠️ **NAMED ONCE, WHICH IS THE HOUSE STYLE AND IS LOAD-BEARING HERE**: the
     * declaration {@see self::verifyingMaterial()} makes and the value
     * {@see self::secret()} reads are the same string by construction, so a
     * census that says *"this endpoint has what it needs"* cannot be describing
     * a different key from the one the 401 turns on.
     */
    public const string CREDENTIAL = 'infobip_webhook_secret';

    /**
     * ⛔ **THREE ENDPOINTS SHARE THIS ONE SECRET AND THEREFORE THIS ONE
     * ANSWER** — inbound, delivery and voice. Its absence is not three faults
     * of a third each: **inbound STOP is the one that decides whether a member
     * of the public who asked us to stop is ever recorded**, and the other two
     * ride the same key.
     */
    public static function verifyingMaterial(): WebhookMaterial
    {
        return WebhookMaterial::credentials([self::CREDENTIAL]);
    }

    /**
     * The header carrying the signature, when the operator has not named one.
     *
     * ⚠️ **A FALLBACK FOR THE COMMON CONFIGURATION, NOT A DOCUMENTED CONSTANT.**
     * Infobip's own note says the name depends on the account's configuration,
     * so this is the value to *start* from and check, not one to trust. It costs
     * nothing to be wrong: an absent header is a refusal, loudly, on the first
     * delivery — never a silent acceptance.
     */
    public const string DEFAULT_HEADER = 'X-Signature';

    /**
     * The Subscriptions/CPaaS X signature header, verbatim from the reference
     * cited in the class docblock.
     *
     * ⚠️ **CHECKED IN ADDITION TO THE CONFIGURED ONE, NEVER INSTEAD OF IT.** An
     * operator who has named a header keeps it.
     */
    public const string EXCHANGE_SIGNATURE_HEADER = 'X-Ib-Exchange-Req-Signature';

    /** The timestamp prepended to the body in that scheme. */
    public const string EXCHANGE_TIMESTAMP_HEADER = 'X-Ib-Exchange-Req-Timestamp';

    /**
     * N176: Infobip's Calls platform signs voice webhooks in X-Ib-Hmac-Signature.
     * Evaluated as a second body-scheme header.
     */
    public const string VOICE_SIGNATURE_HEADER = 'X-Ib-Hmac-Signature';

    /**
     * How old a timestamped signature may be.
     *
     * ⛔ **FIVE MINUTES, AND WHETHER THAT IS WIDE ENOUGH FOR A CARRIER RETRY IS
     * AN OPEN VENDOR QUESTION RATHER THAN A FACT** (4509). This docblock used to
     * assert *"wide enough for a carrier retry"* with no artefact behind it,
     * which is the rule `CLAUDE.md` records five times (255, 277, 684, 1349,
     * 4256) being broken in the file that quotes it. **It was checked on
     * 2026-08-16 and Infobip's public documentation does not answer it:**
     *
     *   - https://www.infobip.com/docs/subscriptions/understanding-subscriptions
     *   - https://www.infobip.com/docs/subscriptions/subscription-components
     *   - https://www.infobip.com/docs/cpaas-x/subscriptions-management/create-manage-subscriptions
     *   - https://www.infobip.com/docs/api/channels/voice/calls/calls-applications/calls-event-webhook
     *
     * None of them says whether a redelivery is re-signed with a fresh timestamp
     * or replays the original one, and none of them publishes the retry schedule
     * — only that retries happen. **If a retry replays the original signature,
     * a genuine delivery our queue could not accept for six minutes is refused
     * permanently**, and on the inbound path that is a dropped STOP. The
     * question is recorded for the Infobip account manager rather than answered
     * by a plausible sentence here.
     *
     * ⚠️ **WHAT THE UNCERTAINTY BUYS IN THE MEANTIME IS VISIBILITY, NOT A
     * GUESS.** A refusal whose HMAC verified and whose timestamp was merely old
     * is logged as exactly that, so the first stale-retry refusal is legible
     * instead of arriving as "the webhook stopped working". See
     * {@see self::exchangeSignatureMatches()}.
     */
    public const int MAX_SIGNATURE_AGE_SECONDS = 300;

    public function maxSignatureAgeSeconds(): int
    {
        return app(DefaultsRegistry::class)->int('sms.webhook.max_signature_age_seconds');
    }

    /**
     * Whether this request carries a valid signature for its own body.
     *
     * Returns a bool rather than throwing: the caller answers 401 either way,
     * and an exception here would tempt somebody into a `try`/`catch` that
     * swallows a verification failure into a 200.
     *
     * N176: The VOICE_SIGNATURE_HEADER is checked after the configured one as a
     * second body-scheme header. The exclusivity reasoning at (4507) applies to it
     * too: if exchange headers are present, we don't evaluate the body-scheme
     * headers, which prevents a fall-through but leaves the replay open (4508).
     */
    public function verify(Request $request): bool
    {
        $secret = $this->secret();

        if ($secret === null) {
            // ⚠️ Fails closed. See the class docblock — this is the branch that
            // decides whether an unconfigured deployment is inert or open.
            return false;
        }

        $body = $request->getContent();

        $scheme = $this->scheme();

        // ⛔ **EXCLUSIVE, NEVER A FALL-THROUGH** (4507). See the class docblock:
        // an `||` over the two constructions lets a captured exchange signature
        // be presented as a body-only one over the base string `T . body`.
        if ($scheme === 'exchange' || $this->carriesExchangeHeaders($request)) {
            return $scheme !== 'body' && $this->exchangeSignatureMatches($request, $body, $secret);
        }

        return $scheme !== 'exchange'
            && ($this->matches($request->header($this->header()), $body, $secret)
                || $this->matches($request->header(self::VOICE_SIGNATURE_HEADER), $body, $secret));
    }

    /**
     * Whether this request claims the Subscriptions/CPaaS X construction at all.
     *
     * ⚠️ **EITHER HEADER, NOT BOTH.** A request carrying a timestamp and no
     * signature, or a signature and no timestamp, is a request about the
     * exchange scheme that cannot satisfy it — and answering it on the body-only
     * branch instead is exactly the fall-through this replaced.
     */
    private function carriesExchangeHeaders(Request $request): bool
    {
        foreach ([self::EXCHANGE_SIGNATURE_HEADER, self::EXCHANGE_TIMESTAMP_HEADER] as $header) {
            $value = $request->header($header);

            if (is_string($value) && $value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * The construction this deployment's notification profile was configured
     * with: `body`, `exchange`, or `auto` for "whichever the headers describe".
     */
    private function scheme(): string
    {
        $configured = config('services.infobip.signature_scheme');

        $value = is_string($configured) ? mb_strtolower(trim($configured)) : '';

        return in_array($value, ['body', 'exchange'], true) ? $value : 'auto';
    }

    /**
     * The Subscriptions/CPaaS X construction — HMAC over `timestamp . body`.
     */
    private function exchangeSignatureMatches(Request $request, string $body, string $secret): bool
    {
        $presented = $request->header(self::EXCHANGE_SIGNATURE_HEADER);
        $timestamp = $request->header(self::EXCHANGE_TIMESTAMP_HEADER);

        if (! is_string($presented) || $presented === '' || ! is_string($timestamp) || $timestamp === '') {
            return false;
        }

        // ⚠️ **THE HEADER'S LITERAL STRING, NOT A REPARSED ONE.** The vendor
        // signed the exact characters it sent; normalising them here — trimming,
        // reformatting, converting seconds to milliseconds — would change the
        // base string and refuse every genuine delivery. Same rule as the raw
        // body, one field over.
        if (! $this->matches($presented, $timestamp.$body, $secret)) {
            return false;
        }

        if ($this->timestampIsFresh($timestamp)) {
            return true;
        }

        // ⛔ **THE MATCH IS COMPUTED BEFORE THE AGE SO THIS LINE CAN EXIST**
        // (4509). `PlatformHealth`'s signature counter has one bucket, so a
        // stale genuine retry and a forgery under the wrong key are the same
        // number on the same screen — and they need opposite fixes. This says
        // which one happened, and only ever for a request whose HMAC verified
        // under our own secret, so it cannot be rung by a stranger.
        //
        // ⚠️ **NO BODY, NO HEADERS, NO SIGNATURE IN THE LINE.** A webhook body
        // routinely carries a member of the public's number; the path and an
        // age in seconds carry the whole of the diagnosis.
        Log::warning('An Infobip webhook signature verified but was refused as stale.', [
            'path' => $request->path(),
            'age_seconds' => $this->ageInSeconds($timestamp),
            'max_age_seconds' => $this->maxSignatureAgeSeconds(),
        ]);

        return false;
    }

    /**
     * Whether a presented signature is the HMAC of this base string.
     *
     * ⚠️ **HEX AND BASE64 ARE BOTH COMPARED** because the encoding, like the
     * header name, is a property of the profile. The Subscriptions reference
     * says *hexadecimal*; the notification-profile documentation does not say.
     * Each comparison is constant-time, and testing both costs one extra hash
     * against an attacker who has to produce a valid one either way.
     *
     * ⛔ **AND THE HEX COMPARISON IS CASE-INSENSITIVE, WHICH IT WAS NOT UNTIL
     * 4510.** `hash_hmac()` returns lower-case hex and `hash_equals()` is a byte
     * comparison, so a profile emitting upper-case hex would have had **every
     * genuine delivery refused** — silently, and on the inbound path that is
     * every STOP dropped. Infobip's public documentation does not state the
     * case (checked 2026-08-16 against the four references cited on
     * {@see self::MAX_SIGNATURE_AGE_SECONDS}), and an unverifiable vendor
     * detail is not something to guess in the direction that fails closed on
     * real traffic. **It costs nothing**: `mb_strtolower` on the presented
     * string cannot turn an invalid signature into a valid one, because the
     * expected value contains no upper-case bytes to lose. Base64 is compared
     * as sent — there case *is* significant.
     *
     * ⛔ **AND INFOBIP'S HMAC PROFILE SENDS `SHA256=<64 hex>` (captured
     * 2026-09-10).** The `SHA256=` prefix is stripped here before the
     * constant-time comparisons run. It is stripped rather than trusted as an
     * algorithm selector: this verifier always hashes with SHA-256 and compares
     * against that expectation, so the prefix is removed to allow the hex
     * comparison to succeed, not read to decide how to hash.
     */
    private function matches(?string $presented, string $baseString, string $secret): bool
    {
        if (! is_string($presented) || $presented === '') {
            return false;
        }

        $presented = trim($presented);
        if (preg_match('/^sha256=/i', $presented)) {
            $presented = preg_replace('/^sha256=/i', '', $presented);
        }

        $expected = hash_hmac('sha256', $baseString, $secret);
        $base64 = base64_encode((string) hex2bin($expected));

        return hash_equals($expected, mb_strtolower($presented)) || hash_equals($base64, $presented);
    }

    /**
     * Whether this timestamp is recent enough to act on.
     *
     * ⚠️ **MILLISECONDS OR SECONDS, DECIDED BY MAGNITUDE.** Infobip's worked
     * example is milliseconds (`1667951050990`) and that is what this expects;
     * the seconds branch exists because refusing a genuine delivery over a unit
     * is the expensive direction, and a value small enough to be seconds cannot
     * be a plausible millisecond timestamp this century.
     *
     * ⛔ **AN UNPARSEABLE TIMESTAMP IS A REFUSAL.** It arrived with a signature
     * that claims to cover it, so a value this application cannot reason about
     * is a value it must not accept.
     */
    private function timestampIsFresh(string $timestamp): bool
    {
        if (! ctype_digit($timestamp)) {
            return false;
        }

        $value = (int) $timestamp;

        $seconds = $value > 100_000_000_000 ? intdiv($value, 1000) : $value;

        return abs(time() - $seconds) <= $this->maxSignatureAgeSeconds();
    }

    /**
     * How far from now this timestamp is, for the stale-refusal log line only.
     *
     * Null when it is unparseable, which is a refusal for a different reason and
     * must not be reported as an age.
     */
    private function ageInSeconds(string $timestamp): ?int
    {
        if (! ctype_digit($timestamp)) {
            return null;
        }

        $value = (int) $timestamp;

        $seconds = $value > 100_000_000_000 ? intdiv($value, 1000) : $value;

        return abs(time() - $seconds);
    }

    /**
     * The header this deployment's notification profile was configured with.
     */
    private function header(): string
    {
        $configured = config('services.infobip.signature_header');

        return is_string($configured) && trim($configured) !== ''
            ? trim($configured)
            : self::DEFAULT_HEADER;
    }

    /**
     * The signing key, or null when there is not one.
     *
     * Through `PlatformCredentials` like every other vendor secret (doc `38`
     * D-149), and the absence is swallowed here rather than propagated because
     * the caller's answer to "no secret" and "bad signature" must be
     * indistinguishable from outside: a 401 that varies would tell an
     * unauthenticated caller whether this endpoint is configured yet.
     */
    private function secret(): ?string
    {
        try {
            return PlatformCredentials::get(self::CREDENTIAL);
        } catch (RuntimeException) {
            return null;
        }
    }
}

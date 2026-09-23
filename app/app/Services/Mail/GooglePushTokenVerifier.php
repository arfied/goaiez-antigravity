<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Contracts\VerifiesWebhookSenders;
use App\Enums\WebhookVerification;
use App\Services\Config\DefaultsRegistry;
use App\Support\VendorLog;
use App\Support\WebhookMaterial;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Whether a Cloud Pub/Sub push actually came from Google, on our subscription.
 *
 * ⚠️ **READ GOOGLE'S OWN PAGES ON 2026-08-12 RATHER THAN ASSUMING IT WORKS LIKE
 * THE TWO WEBHOOK VERIFIERS THIS APPLICATION ALREADY HAS, AND IT WORKS LIKE
 * NEITHER.** `InfobipWebhookVerifier` is an HMAC over the raw body with a shared
 * secret; `SnsMessageVerifier` is an RSA signature over a canonicalised field
 * list, with the certificate named inside the message. Pub/Sub is a third shape:
 *
 * ⛔ **THE BODY IS NOT SIGNED. AT ALL.** Google attaches an OpenID Connect JWT in
 * the `Authorization` header (*"If a push subscription uses authentication, the
 * Pub/Sub service signs a JWT and sends the JWT in the authorization header of
 * the push request"* — `cloud.google.com/pubsub/docs/push`, read 2026-08-12,
 * page dated 2026-07-29). The token attests **who is calling**. It attests
 * nothing whatever about what they said, and no amount of verifying it makes the
 * payload trustworthy. That is why `GmailPushController` reads the mail back
 * from Google over its own connection instead of believing the request.
 *
 * ## The five checks, quoted
 *
 * From `cloud.google.com/pubsub/docs/authenticate-push-subscriptions` (read
 * 2026-08-12, page dated 2026-07-30), which lists what a receiving endpoint
 * should do — each one below fails silently if skipped:
 *
 *   1. *"Verify JWT signature using Google's public certificates"*
 *   2. *"Confirm the `email` claim matches the expected service account"*
 *   3. *"Validate `email_verified` is `true`"*
 *   4. *"Check the `aud` claim against your endpoint URL"*
 *   5. *"Verify issuer is `https://accounts.google.com`"*
 *
 * ⚠️ **CHECK 2 IS THE ONE THAT DOES THE WORK AND IT IS THE EASIEST TO OMIT.**
 * The signature and the issuer prove only that *Google* minted the token —
 * Google mints these for every Pub/Sub subscription on the planet, so an
 * endpoint that stopped after check 1 would accept a genuine, correctly signed,
 * perfectly valid token belonging to somebody else's project. The `email` claim
 * is the whole tenancy of this endpoint.
 *
 * ⚠️ **AND CHECK 4 IS WHAT STOPS A REPLAY AGAINST A DIFFERENT ENDPOINT.** The
 * `aud` is the push URL configured on the subscription, so a token captured from
 * one of our other endpoints — or from any endpoint anywhere — does not verify
 * here.
 *
 * ⚠️ **IT FAILS CLOSED ON MISSING CONFIGURATION**, which is the state a fresh
 * install is in: no audience or no service account means every request is
 * refused, including genuine ones. Both sibling verifiers record the same
 * decision, and the reason is the same one: the permissive branch leaves the
 * endpoint open to the internet and it reports as working.
 *
 * ⛔ **`hash_equals` FOR THE TWO STRING COMPARISONS.** Neither claim is secret,
 * so this is belt and braces rather than load-bearing — but a `===` here is the
 * habit that gets copied into the next verifier where it is not.
 *
 * ## ⛔ Not being able to check is a third outcome, and this file already named
 * the hazard while acting on half of it
 *
 * ⛔ **THE KEY FETCH HAD THREE SILENT `return []` ARMS AND EVERY ONE OF THEM
 * REACHED THE CALLER AS *"THIS TOKEN DID NOT VERIFY"*** (9380–9394) — counted as
 * `PlatformHealthSignal::WebhookSignature`, reported to an operator as a missing
 * or rotated signing secret of ours. ⚠️ **`signingKeys()`'s own comment has
 * described the consequence since the day it shipped** — *"discards every
 * genuine notification in that hour as a forgery — silently, because a refused
 * push is not an error anywhere"* — **and acted only on the caching half.** The
 * silence was the other half, and this is it: {@see WebhookVerification} carries
 * the third outcome out, and `GmailPushController` counts it as its own fault.
 *
 * ⛔ **AND `Http::get()` THROWS RATHER THAN RETURNING A FAILED RESPONSE WHEN THE
 * HOST DOES NOT ANSWER.** Nothing here caught {@see ConnectionException},
 * nothing on the path to it does, and no handler is registered — so an
 * unreachable `oauth2/v3/certs` answered `POST /webhooks/gmail` with a **500**
 * and incremented nothing.
 *
 * ⛔ **EVERY FAILURE ARM IS REPORTED HERE, WHICH IS NOT TRUE OF THE SIBLING
 * VERIFIER, AND THE DIFFERENCE IS ABOUT WHO CHOSE THE URL.** The address fetched
 * here is `platform_mail.gmail.push.certificate_endpoint` — **ours**, from
 * configuration, unreachable by anything a caller sends — so a stranger cannot
 * manufacture any of these faults and every one of them is genuinely Google's or
 * ours. {@see SnsMessageVerifier} fetches an address written inside the message
 * it is checking and therefore reports only a 5xx; **that asymmetry is deliberate
 * and is stated in both files, because a lane levelling the two would silently
 * hand a stranger a bell.**
 */
final class GooglePushTokenVerifier implements VerifiesWebhookSenders
{
    /** The `aud` claim every push token must carry. */
    public const string AUDIENCE = 'platform_mail.gmail.push.audience';

    /** The service account whose `email` claim identifies Google's pusher. */
    public const string SERVICE_ACCOUNT = 'platform_mail.gmail.push.service_account';

    /**
     * ⛔ **TWO PATHS, NOT THE THREE THIS ENDPOINT READS, AND THE THIRD IS LEFT
     * OUT DELIBERATELY.** `platform_mail.gmail.push.issuer_host` is also
     * load-bearing — {@see self::acceptedIssuers()} returns an empty list
     * without it and every token is refused — but it is a **literal in
     * `config/platform_mail.php` with no environment variable behind it**, so no deployment
     * can be missing it and a census row for it would read *set* on every
     * install for ever. **A row that cannot take its negative value is
     * decoration**, which is the shape `CLAUDE.md` names over
     * `operator_alerts.emailed_at`. The two below are the ones an operator
     * supplies and the two that are blank on a fresh install.
     */
    public static function verifyingMaterial(): WebhookMaterial
    {
        return WebhookMaterial::configuration([self::AUDIENCE, self::SERVICE_ACCOUNT]);
    }

    /**
     * How long Google's signing keys are cached.
     *
     * The OpenID Connect guide: *"Since Google changes its public keys only
     * infrequently, you can cache them using the cache directives of the HTTP
     * response."* An hour is well inside any rotation and keeps the fetch off
     * the hot path of a busy mailbox — `SnsMessageVerifier` picked the same
     * number for the same reason.
     */
    public const int CERTIFICATE_TTL_SECONDS = 3600;

    public function certificateTtlSeconds(): int
    {
        return app(DefaultsRegistry::class)->int('mail.google_push.certificate_ttl_seconds');
    }

    private const string CERTIFICATE_CACHE_KEY = 'platform-mail:gmail:push-keys';

    /**
     * Whether this `Authorization` header carries a token we should act on.
     *
     * Takes the header value rather than the request, so that no implementation
     * here can be tempted into reading the body — the body is not covered by
     * anything this class checks and must not look as though it is.
     *
     * Returns a value rather than throwing, on `InfobipWebhookVerifier`'s
     * reasoning: the caller answers a refusal either way, and an exception
     * invites a `try`/`catch` that swallows a verification failure into a 200.
     *
     * ⚠️ **THE RETURN TYPE WIDENED FROM `bool` AT 9380–9394 AND THAT REASONING
     * IS UNCHANGED** — {@see WebhookVerification} is still a value and still not
     * a throw. What it adds is the outcome the `bool` could not carry, and
     * fail-closed survives it structurally: only
     * {@see WebhookVerification::Verified} is a pass.
     */
    public function verify(?string $authorization): WebhookVerification
    {
        $audience = self::configured(self::AUDIENCE);
        $serviceAccount = self::configured(self::SERVICE_ACCOUNT);

        if ($audience === null || $serviceAccount === null) {
            // Fails closed. See the class docblock — this is the branch that
            // decides whether an unconfigured deployment is inert or open.
            //
            // ⛔ **AND IT IS A REFUSAL RATHER THAN A KEY FAULT.** An install
            // that has not been configured yet is not Google failing, and
            // ringing a vendor bell about it would page every fresh deployment
            // on its first stray request (511).
            return WebhookVerification::Refused;
        }

        $token = self::bearer($authorization);

        if ($token === null) {
            return WebhookVerification::Refused;
        }

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return WebhookVerification::Refused;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = self::decodeSegment($encodedHeader);
        $claims = self::decodeSegment($encodedPayload);
        $signature = self::base64UrlDecode($encodedSignature);

        if ($header === null || $claims === null || $signature === null) {
            return WebhookVerification::Refused;
        }

        // ⚠️ **THE ALGORITHM IS PINNED AND NOT READ AS AN INSTRUCTION.** A
        // verifier that honours whatever `alg` the token names accepts `none`,
        // and accepts HS256 signed with the public key it was about to verify
        // against. The discovery document says Google signs these RS256 and
        // nothing else is entertained.
        if (($header['alg'] ?? null) !== 'RS256') {
            return WebhookVerification::Refused;
        }

        $keys = $this->signingKeys();

        // ⛔ **AN EMPTY KEY SET IS *"WE COULD NOT CHECK"* AND NOT *"IT DID NOT
        // CHECK OUT"*, AND THE TWO USED TO LEAVE HERE AS THE SAME VALUE.**
        // `signingKeys()` returns `[]` for an unreachable host, a non-2xx, a
        // body with no `keys` array and a set of keys none of which converts —
        // every one of them a fact about Google or about us, none of them a fact
        // about the sender.
        if ($keys === []) {
            return WebhookVerification::KeysUnavailable;
        }

        $kid = is_string($header['kid'] ?? null) ? $header['kid'] : null;

        // ⚠️ **A TOKEN NAMING A `kid` GOOGLE IS PUBLISHING NOTHING FOR IS A
        // REFUSAL AND NOT A KEY FAULT** — we have the keys, and this is not one
        // of them. See {@see self::signingKeys()}.
        $key = $kid === null ? null : ($keys[$kid] ?? null);

        if ($key === null) {
            return WebhookVerification::Refused;
        }

        $verified = openssl_verify(
            $encodedHeader.'.'.$encodedPayload,
            $signature,
            $key,
            OPENSSL_ALGO_SHA256,
        );

        if ($verified !== 1) {
            return WebhookVerification::Refused;
        }

        return $this->claimsAreOurs($claims, $audience, $serviceAccount)
            ? WebhookVerification::Verified
            : WebhookVerification::Refused;
    }

    /**
     * Checks 2 to 5, plus the expiry.
     *
     * ⚠️ **RUN ONLY AFTER THE SIGNATURE, AND THE ORDER IS NOT COSMETIC.** These
     * read fields out of a token; before the signature is checked, those fields
     * are whatever the caller typed.
     *
     * @param  array<string, mixed>  $claims
     */
    private function claimsAreOurs(array $claims, string $audience, string $serviceAccount): bool
    {
        $issuer = $claims['iss'] ?? null;

        if (! is_string($issuer) || ! in_array($issuer, self::acceptedIssuers(), true)) {
            return false;
        }

        $presentedAudience = $claims['aud'] ?? null;

        if (! is_string($presentedAudience) || ! hash_equals($audience, $presentedAudience)) {
            return false;
        }

        $email = $claims['email'] ?? null;

        if (! is_string($email) || ! hash_equals($serviceAccount, $email)) {
            return false;
        }

        // ⚠️ **`=== true`, NOT TRUTHY.** The claim is a JSON boolean; a string
        // `"false"` is truthy in PHP and would pass a loose test while saying
        // the opposite of what it means.
        if (($claims['email_verified'] ?? null) !== true) {
            return false;
        }

        $expiry = $claims['exp'] ?? null;

        if (! is_int($expiry)) {
            return false;
        }

        $skew = (int) config('platform_mail.gmail.push.clock_skew_seconds', 60);

        return $expiry + $skew > time();
    }

    /**
     * Google's published keys, converted to PEM and keyed by `kid`.
     *
     * ⛔ **AN EMPTY RETURN MEANS *"WE HAVE NO KEYS"* AND NOTHING ELSE, WHICH IS
     * WHAT LETS `verify()` CALL IT A VENDOR FAULT** (9380–9394). Every arm here
     * is a fact about Google's endpoint or about this install's ability to reach
     * it; nothing a caller sends can influence any of them, because the address
     * is configuration. ⚠️ **`publicKey()` USED TO SIT BETWEEN THIS AND
     * `verify()` AND IS GONE**: it collapsed *"no keys at all"* and *"not this
     * `kid`"* into one `null`, which is exactly the distinction the caller now
     * needs. Its own reasoning survives at the call site — **a token naming an
     * unknown `kid` is refused rather than checked against every key**, because
     * trying them all means a rotation can never be observed and turns a forged
     * `kid` into a loop over every key on a public endpoint.
     *
     * ⚠️ **`VendorLog::failure()` ON EVERY ARM, AND THIS FILE PREVIOUSLY LOGGED
     * NOTHING AT ALL.** The counter says *that* the keys were unavailable for
     * this endpoint; which of the four arms it was lives here, because a fixed
     * label is the only thing `VendorLog::failure()` permits and it is what an
     * operator reads after the bell.
     *
     * @return array<string, string>
     */
    private function signingKeys(): array
    {
        $cached = Cache::get(self::CERTIFICATE_CACHE_KEY);

        if (is_array($cached) && $cached !== []) {
            /** @var array<string, string> $cached */
            return $cached;
        }

        $endpoint = (string) config('platform_mail.gmail.push.certificate_endpoint');

        try {
            $response = Http::timeout((int) config('platform_mail.gmail.push.certificate_timeout', 5))
                ->get($endpoint);
        } catch (ConnectionException) {
            // ⛔ Without this the throw leaves a `POST /webhooks/gmail` request
            // as a 500, past a caller that catches nothing and an application
            // that registers no handler for it.
            VendorLog::failure('google-oauth2', 'GET', $endpoint, ConnectionException::class);

            return [];
        }

        if ($response->failed()) {
            VendorLog::failure('google-oauth2', 'GET', $endpoint, 'http_'.$response->status());

            return [];
        }

        $keys = $response->json('keys');

        if (! is_array($keys)) {
            VendorLog::failure('google-oauth2', 'GET', $endpoint, 'no_keys_in_body');

            return [];
        }

        $converted = [];

        foreach ($keys as $key) {
            if (! is_array($key)) {
                continue;
            }

            $kid = $key['kid'] ?? null;
            $modulus = $key['n'] ?? null;
            $exponent = $key['e'] ?? null;

            if (! is_string($kid) || ! is_string($modulus) || ! is_string($exponent)) {
                continue;
            }

            if (($key['kty'] ?? null) !== 'RSA') {
                continue;
            }

            $pem = self::pemFromRsaJwk($modulus, $exponent);

            if ($pem !== null) {
                $converted[$kid] = $pem;
            }
        }

        if ($converted === []) {
            // ⚠️ **AN EMPTY SET IS NOT CACHED.** `SnsMessageVerifier` records
            // the same hazard from the other direction: a key host answering
            // 200 with something unusable, cached for an hour, discards every
            // genuine notification in that hour as a forgery — silently,
            // because a refused push is not an error anywhere.
            //
            // ⛔ **AND SINCE 9380–9394 IT IS NO LONGER SILENT**, which is the
            // half this comment described and did not close: the caller reports
            // it as `PlatformHealthSignal::WebhookKeyUnavailable` rather than as
            // an unverifiable signature.
            VendorLog::failure('google-oauth2', 'GET', $endpoint, 'no_usable_key');

            return [];
        }

        Cache::put(self::CERTIFICATE_CACHE_KEY, $converted, $this->certificateTtlSeconds());

        return $converted;
    }

    /**
     * One RSA JWK as a PEM `SubjectPublicKeyInfo`.
     *
     * ⚠️ **THIS EXISTS BECAUSE THE DOCUMENTED ENDPOINT SERVES JWK AND `openssl`
     * READS PEM.** Google's discovery document names `oauth2/v3/certs` as the
     * `jwks_uri`, and its OpenID Connect guide tells an implementer to take the
     * URL from there. The legacy `oauth2/v1/certs` endpoint answers with
     * ready-made PEM certificates and would delete this method — it is not what
     * Google documents, and this codebase has four recorded instances of a
     * plausible vendor string being the wrong one.
     *
     * The structure, from RFC 5280 §4.1 and RFC 8017 §A.1.1:
     *
     *   SEQUENCE {
     *     SEQUENCE { OID 1.2.840.113549.1.1.1 (rsaEncryption), NULL }
     *     BIT STRING { SEQUENCE { INTEGER modulus, INTEGER exponent } }
     *   }
     */
    private static function pemFromRsaJwk(string $modulus, string $exponent): ?string
    {
        $n = self::base64UrlDecode($modulus);
        $e = self::base64UrlDecode($exponent);

        if ($n === null || $e === null || $n === '' || $e === '') {
            return null;
        }

        $rsaKey = self::derSequence(self::derInteger($n).self::derInteger($e));

        $algorithm = self::derSequence(
            // OID 1.2.840.113549.1.1.1, then the ASN.1 NULL parameters
            // rsaEncryption is defined to carry.
            "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00"
        );

        // A BIT STRING's first content byte counts the unused trailing bits,
        // which for a whole number of bytes is zero. Omitting it produces a
        // structure openssl rejects with no explanation.
        $bitString = "\x03".self::derLength(strlen($rsaKey) + 1)."\x00".$rsaKey;

        $encoded = base64_encode(self::derSequence($algorithm.$bitString));

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split($encoded, 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    private static function derSequence(string $contents): string
    {
        return "\x30".self::derLength(strlen($contents)).$contents;
    }

    /**
     * A DER INTEGER holding an unsigned big-endian number.
     *
     * ⚠️ **THE LEADING ZERO IS NOT OPTIONAL.** DER integers are signed, so a
     * modulus whose top bit is set — which is true of essentially every RSA
     * modulus — is a negative number without it, and openssl reads a different
     * key.
     */
    private static function derInteger(string $value): string
    {
        if ($value !== '' && (ord($value[0]) & 0x80) !== 0) {
            $value = "\x00".$value;
        }

        return "\x02".self::derLength(strlen($value)).$value;
    }

    private static function derLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = '';

        while ($length > 0) {
            $bytes = chr($length & 0xFF).$bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    /**
     * The token out of a `Bearer` header, or null.
     */
    private static function bearer(?string $authorization): ?string
    {
        if (! is_string($authorization)) {
            return null;
        }

        $authorization = trim($authorization);

        // Case-insensitive on the scheme, which RFC 7235 requires; the token
        // itself is untouched.
        if (preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @return ?array<string, mixed>
     */
    private static function decodeSegment(string $segment): ?array
    {
        $decoded = self::base64UrlDecode($segment);

        if ($decoded === null) {
            return null;
        }

        $parsed = json_decode($decoded, true);

        return is_array($parsed) ? $parsed : null;
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $padded = strtr($value, '-_', '+/');
        $remainder = strlen($padded) % 4;

        if ($remainder !== 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($padded, true);

        return $decoded === false ? null : $decoded;
    }

    /**
     * The two spellings of Google's issuer, built from a bare host.
     *
     * ⚠️ **THE HOST IS CONFIGURED WITHOUT A SCHEME ON PURPOSE**, and the config
     * block says why at length: written as a URL it would be read by
     * `outboundHostsInCode()` as a destination, and the subprocessor inventory
     * would demand a row for a host nothing in this application dials — §5 has
     * it un-backticked precisely because it is *"a redirect target that appears
     * in no file of ours"*. Assembling it here keeps the validation value a
     * validation value.
     *
     * Google's OpenID Connect guide asks for both forms to be accepted; neither
     * is guessed at and neither is dropped.
     *
     * @return list<string>
     */
    private static function acceptedIssuers(): array
    {
        $host = self::configured('platform_mail.gmail.push.issuer_host');

        if ($host === null) {
            return [];
        }

        return ['https://'.$host, $host];
    }

    /**
     * A configured string, or null when it is absent or blank.
     */
    private static function configured(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}

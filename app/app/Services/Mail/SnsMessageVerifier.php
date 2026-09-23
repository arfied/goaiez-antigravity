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
 * Whether an SNS delivery actually came from Amazon.
 *
 * ⚠️ **READ AWS'S OWN PAGE ON 2026-08-11 RATHER THAN ASSUMING IT WORKS LIKE
 * STRIPE'S OR INFOBIP'S, AND IT DOES NOT WORK LIKE EITHER.** There is no shared
 * secret and no HMAC. SNS signs the message with a private key and publishes the
 * matching certificate at a URL **inside the message it just signed**, which is
 * the part that looks like a vulnerability and is not — provided the URL is
 * constrained before it is fetched. AWS's own guidance, quoted because each
 * bullet fails silently if skipped:
 *
 *   - *"Always retrieve the signing certificate using HTTPS."*
 *   - *"The certificate should come from an SNS-signed URL."*
 *   - *"Don't trust any certificates provided in the message without
 *     validation."*
 *   - *"Reject any message with an unexpected `TopicArn` to prevent spoofing."*
 *
 * ⛔ **AN UNCONSTRAINED `SigningCertURL` IS THE WHOLE ATTACK.** Fetch whatever
 * the message names, and an attacker publishes their own certificate on their
 * own host, signs their own payload with the matching key, and this endpoint
 * verifies it perfectly — then suppresses any email address on the platform.
 * The URL is therefore constrained **before** the fetch, and it is constrained
 * with AWS's own rule rather than an approximation of it: see
 * `certificateUrlIsAllowed()`, which shipped with a prefix-and-suffix test that
 * admitted `https://sns.evil.s3.amazonaws.com/c.pem` (4450). The pattern is
 * held in `config/platform_mail.php` with no scheme on it, for the reason that
 * file gives: a literal URL in a validation rule reads to
 * `outboundHostsInCode()` as a destination.
 *
 * ## The string that is signed
 *
 * Field-name, newline, value, newline — in the fixed order below, which is
 * alphabetical over the fields present. `Subject` appears only when the message
 * carries one, and omitting it when present (or including it when absent) makes
 * every signature fail with no other symptom.
 *
 *   Notification                Message, MessageId, Subject?, Timestamp,
 *                               TopicArn, Type
 *   SubscriptionConfirmation    Message, MessageId, SubscribeURL, Timestamp,
 *                               Token, TopicArn, Type
 *
 * `SignatureVersion` 1 is SHA1 and 2 is SHA256; both signatures are base64.
 * Version 2 is the recommended one and both are accepted, because the topic's
 * version is an AWS console setting we do not control from here. ⛔ **And
 * nothing else is accepted — an allowlist, never a default** (4452). A ternary
 * on `=== '2'` sends every unrecognised value down the SHA-1 branch, which lets
 * the sender of an unverified message choose the weaker algorithm; AWS's own
 * validator refuses any version that is not `1` or `2`.
 *
 * ⚠️ **THE STRING ENDS WITH A NEWLINE, AND AWS'S OWN PAGE APPEARS TO SAY IT
 * MUST NOT — RE-VERIFIED 2026-08-16 BEFORE THE R16 FLIP, BECAUSE GETTING THIS
 * WRONG WOULD MAKE EVERY GENUINE BOUNCE UNVERIFIABLE AND SUPPRESS NOBODY.**
 * *Verifying the signature of an Amazon SNS message when using HTTP query-based
 * requests* says **"Do not add a newline character at the end of the string"**,
 * and this implementation appends one after the last value. It is right and the
 * sentence is about the page's own bash example: that example pipes the string
 * through `echo -e`, which supplies the final newline itself, so the
 * instruction is not to add a *second* one. AWS's own PHP validator
 * (`aws/aws-php-sns-message-validator`, `MessageValidator::getStringToSign()`,
 * read 2026-08-16) builds `"{$key}\n{$message[$key]}\n"` for every signable
 * field including the last. **The SDK is the artefact, the prose is the
 * summary, and `CLAUDE.md` says to verify against the artefact.**
 *
 * ⚠️ **AND THE TESTS CANNOT SETTLE IT, WHICH IS WHY IT WAS CHECKED AGAINST
 * AWS.** `SnsMessageVerifierTest` mints a real key pair and calls a genuine
 * `openssl_sign`, so the cryptography is real — but it builds its string to
 * sign with the same field-and-newline rule this class uses, so it would pass
 * just as well over a format AWS never produces. It proves the signature check;
 * it says nothing about the wire format, and the honest place to record that is
 * here rather than in a test name that implies otherwise.
 *
 * ## ✅ Half of that is no longer true, and exactly which half is the point
 *
 * ✅ **A GENUINE AMAZON MESSAGE VERIFIED IN PRODUCTION ON 2026-08-26**
 * (10220–10229). `SesFeedbackController` logged *"An SNS subscription
 * confirmation arrived and was not auto-confirmed"* with the real topic named,
 * and that line sits **after** `verify()` returned
 * {@see WebhookVerification::Verified} — so on real AWS bytes, over the network,
 * the whole chain held: the `SigningCertURL` constraint admitted Amazon's own
 * host, the certificate was fetched and parsed, and the signature verified
 * against the **`SubscriptionConfirmation`** field list *as written here,
 * including the trailing newline that AWS's own prose appears to forbid*.
 * **The doubt this class carried about that rule is answered by the wire.**
 *
 * ⛔ **AND THE `Notification` HALF WAS UNTOUCHED BY IT — TRUE THAT MORNING AND
 * NOT ANY LONGER, CORRECTED THE SAME DAY, 2026-08-26 (10460–10479).** This
 * paragraph said *"no genuine one has ever reached this application … So the
 * optional-`Subject` rule … is still proven only against a fixture that shares
 * its assumption."* **Both clauses are false.** `mail:probe-ses-simulator`
 * sent three probes through AWS's sandbox-exempt mailbox simulator and
 * produced five real `Notification` messages, all genuine and all verified —
 * zero refused as unverified, zero certificate-fetch failures:
 *
 *   bounce    : 2026-08-26T19:38:26+00:00
 *   complaint : 2026-08-26T19:41:47+00:00
 *   delivery  : 2026-08-26T19:41:47+00:00
 *
 * Complaint and delivery share a timestamp because **one probe produced
 * both** — a complaint means the message *was* delivered and then complained
 * about. **The `Notification` field set — `Message`, `MessageId`, `Subject?`,
 * `Timestamp`, `TopicArn`, `Type`, with `Subject` omitted entirely when
 * absent — is proven against real AWS bytes, over the network, for all three
 * event types this application reads**, and the optional-`Subject` rule this
 * paragraph singled out as the likeliest thing to be wrong held on every one
 * of the five.
 *
 * ⚠️ **THREE THINGS THAT DELIVERY STILL DOES NOT PROVE, NAMED SO THEY ARE NOT
 * READ INTO IT — ONE OF THE ORIGINAL THREE IS NOW RETIRED RATHER THAN CARRIED
 * FORWARD UNCHANGED.** It does not say which `SignatureVersion` that topic
 * uses, so one of the two algorithm arms is still exercised by nothing but
 * the suite. It is five messages through one simulator account rather than a
 * sustained feed. ⛔ **And none of the five is a real mailbox provider's
 * bounce** — AWS's simulator produces a payload of its own choosing, and
 * whether a genuine bounce from a real recipient's mail server matches that
 * shape is unproven and stays unproven until SES production access and a
 * real recipient exist, neither of which any lane in this repository can
 * produce. ⚠️ **Retired**: the original third item said this "says nothing
 * about the two SES event shapes {@see MailFeedback} reads, which ride
 * inside `Message` and are not signed field by field" — the bounce and
 * complaint probes above are exactly what `MailFeedback` parsed to write the
 * three heartbeats shown, so that gap is closed rather than merely narrowed.
 *
 * ⚠️ **IT FAILS CLOSED ON AN EMPTY TOPIC ALLOWLIST**, which is the state a
 * fresh install is in. `InfobipWebhookVerifier` records the same decision and
 * the same reason: the permissive branch here leaves an endpoint that can
 * suppress any address on the platform open to the internet, and it would report
 * as working.
 *
 * ## ⛔ Not being able to check is a third outcome, and it used to be two others
 *
 * ⛔ **THIS CLASS ANSWERED A BARE `bool` UNTIL 2026-08-25 AND TWO DIFFERENT
 * FAULTS CAME OUT OF IT WEARING ONE ANSWER** (9380–9394). The certificate is
 * fetched over the network, and that fetch has always had failure arms — a
 * non-2xx and a body that is not a certificate — which returned `null` **with no
 * log line anywhere in this file**, so the caller answered `401`, counted
 * `PlatformHealthSignal::WebhookSignature`, and an operator eventually read
 * *"the usual cause is a missing or rotated signing secret"* about an outage at
 * AWS. {@see WebhookVerification} is the third outcome.
 *
 * ⛔ **AND THE WORSE HALF HAD NO OUTCOME AT ALL: `Http::get()` DOES NOT RETURN A
 * FAILED RESPONSE WHEN THE HOST DOES NOT ANSWER — IT THROWS.** A DNS failure, a
 * refused connection or a five-second timeout raised
 * {@see ConnectionException} out of `certificate()`, past a caller that catches
 * nothing, into an application with no handler registered for it: **a 500 out of
 * a public webhook endpoint, and no counter of any kind incremented.** It is
 * caught here now.
 *
 * ## ⛔ Only a 5xx is reported as the vendor's fault, and the reason is who
 * chose the URL
 *
 * ⛔ **`SigningCertURL` IS WRITTEN BY WHOEVER POSTED THE MESSAGE**, constrained
 * to `sns.<region>.amazonaws.com/….pem` and still theirs — so a stranger who
 * knows our topic ARN can name a well-formed address that does not resolve and
 * **manufacture** a connection failure at will. A bell that rang on the first
 * one would be a bell a stranger rings, which is worse than the wrong sentence
 * it replaced.
 *
 * ✅ **What a sender cannot manufacture is a 5xx from AWS's own host**: a name
 * they invent fails to resolve, and a path they invent on a real host answers
 * 403 or 404. That arm alone answers {@see WebhookVerification::KeysUnavailable}.
 *
 * ⚠️ **SO THE RESIDUAL IS STATED RATHER THAN LEFT TO BE DISCOVERED: A GENUINE
 * DNS OR EGRESS FAILURE TO AWS STILL READS HERE AS A REFUSAL.** It no longer
 * takes the request down and it is no longer invisible — `VendorLog::failure()`
 * records every arm — but it is counted as an unverifiable signature, because
 * this class cannot tell it apart from a stranger naming a host that is not
 * there. {@see GooglePushTokenVerifier} has no such residual and reports every
 * arm, because the address it fetches is **ours**. **The two are asymmetric on
 * purpose and the asymmetry is about the URL, not about the vendor.**
 */
final class SnsMessageVerifier implements VerifiesWebhookSenders
{
    /**
     * The topics this endpoint will accept a message from.
     *
     * ⛔ **NOT A CREDENTIAL ROW, AND IT NEVER WILL BE.** The keys this class
     * verifies with are fetched from AWS's own host per message; what an
     * operator supplies is the **identity** those keys are checked against, and
     * {@see self::topicIsAllowed()} fails closed on an empty list before the
     * cryptography is reached at all. ⚠️ **So a census written only against
     * `CredentialStore` would report this endpoint as fine while it refused
     * every bounce and complaint AWS published** — which on a platform whose
     * mail rides SES is the feedback loop going silent with nothing to say so.
     *
     * ⚠️ **CONFIRMING THE SUBSCRIPTION IS A SEPARATE, MANUAL STEP AND THIS
     * CANNOT SEE IT** (`.env.example` is the authority for the sequence). A
     * populated topic list means this endpoint would judge a message; it does
     * not mean AWS is publishing one.
     */
    public const string TOPIC_ALLOWLIST = 'platform_mail.sns.topic_arns';

    public static function verifyingMaterial(): WebhookMaterial
    {
        return WebhookMaterial::configuration([self::TOPIC_ALLOWLIST]);
    }

    /**
     * How long a fetched certificate is cached.
     *
     * AWS rotates these, so it cannot be pinned; an hour keeps the fetch off
     * the hot path of a bounce storm without outliving a rotation for long.
     */
    public const int CERTIFICATE_TTL_SECONDS = 3600;

    public function certificateTtlSeconds(): int
    {
        return app(DefaultsRegistry::class)->int('mail.sns.certificate_ttl_seconds');
    }

    /**
     * Whether this message is genuinely from Amazon — or whether we could not
     * find out.
     *
     * ⚠️ **THE RETURN TYPE WIDENED FROM `bool` AT 9380–9394 AND FAIL-CLOSED IS
     * WHAT SURVIVES THE WIDENING**: {@see WebhookVerification::Verified} is the
     * only case a caller may act on, and a caller that forgets an arm gets an
     * `UnhandledMatchError` rather than a quiet acceptance.
     *
     * @param  array<string, mixed>  $message
     */
    public function verify(array $message): WebhookVerification
    {
        if (! $this->topicIsAllowed($message)) {
            return WebhookVerification::Refused;
        }

        $signature = $message['Signature'] ?? null;
        $certUrl = $message['SigningCertURL'] ?? null;

        if (! is_string($signature) || $signature === '' || ! is_string($certUrl)) {
            return WebhookVerification::Refused;
        }

        if (! $this->certificateUrlIsAllowed($certUrl)) {
            return WebhookVerification::Refused;
        }

        // ⛔ **AN ALLOWLIST, BECAUSE THE ALTERNATIVE LETS THE SENDER PICK THE
        // ALGORITHM** (4452). This read
        // `($message['SignatureVersion'] ?? '1') === '2' ? SHA256 : SHA1`, so
        // every unrecognised value — a typo, a future version 3, or a chosen
        // `"0"` — fell through to **SHA-1**, silently downgrading the strongest
        // branch to the weakest. AWS's own validator throws on any version that
        // is not `1` or `2` (`MessageValidator::getStringToSign()`, read
        // 2026-08-16); a message with no `SignatureVersion` at all is likewise
        // refused rather than defaulted, because a missing field is not a
        // statement that the signature is SHA-1.
        //
        // ⚠️ Before the certificate is fetched, so a junk version costs no
        // outbound request. Forging SHA-1 against a 2048-bit RSA key remains
        // impractical — this is closed as a weakness rather than an exploit.
        $sigVersion = $message['SignatureVersion'] ?? null;
        $algorithm = null;
        if ($sigVersion === '1') {
            $algorithm = OPENSSL_ALGO_SHA1;
        } elseif ($sigVersion === '2') {
            $algorithm = OPENSSL_ALGO_SHA256;
        }

        if ($algorithm === null) {
            return WebhookVerification::Refused;
        }

        $stringToSign = $this->stringToSign($message);

        if ($stringToSign === null) {
            return WebhookVerification::Refused;
        }

        $certificate = $this->certificate($certUrl);

        // ⛔ **A `KeysUnavailable` HERE IS RETURNED RATHER THAN FALLING THROUGH
        // TO THE REFUSAL BELOW.** Nothing about the sender has been judged at
        // this point, and reporting "did not verify" about a check that never
        // ran is the whole defect this class carried.
        //
        // ⛔ **THE ONE CASE IS NAMED AND THE VALUE IS NOT PASSED THROUGH, WHICH
        // IS NOT PEDANTRY.** `certificate()`'s return type cannot say *"this
        // enum, but only that one case"*, so `return $certificate` would make a
        // later edit answering `Verified` from the fetch a **fail-open** path
        // through the signature check — and it would read on a diff as a
        // simplification. Anything that is not the certificate and is not this
        // one case falls to the refusal below.
        if ($certificate === WebhookVerification::KeysUnavailable) {
            return WebhookVerification::KeysUnavailable;
        }

        if (! is_string($certificate)) {
            return WebhookVerification::Refused;
        }

        $publicKey = openssl_pkey_get_public($certificate);

        if ($publicKey === false) {
            return WebhookVerification::Refused;
        }

        $decoded = base64_decode($signature, true);

        if ($decoded === false) {
            return WebhookVerification::Refused;
        }

        return openssl_verify($stringToSign, $decoded, $publicKey, $algorithm) === 1
            ? WebhookVerification::Verified
            : WebhookVerification::Refused;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function topicIsAllowed(array $message): bool
    {
        $allowed = config(self::TOPIC_ALLOWLIST);

        if (! is_array($allowed) || $allowed === []) {
            // Fails closed. See the class docblock — this is the branch that
            // decides whether an unconfigured deployment is inert or open.
            return false;
        }

        $arn = $message['TopicArn'] ?? null;

        return is_string($arn) && in_array($arn, $allowed, true);
    }

    /**
     * ⚠️ **PARSED WITH `parse_url()` AND CHECKED PART BY PART.** A
     * `str_starts_with($url, 'https://sns.')` test is defeated by
     * `https://sns.evil.test/`, and a `str_contains($url, '.amazonaws.com')` by
     * `https://evil.test/?x=.amazonaws.com` — decision 223's line, that
     * `parse_url()` runs first in every case because matching a pattern against
     * a whole URL string is how a redirect gets through.
     *
     * ⛔ **A PREFIX AND A SUFFIX ARE NOT ENOUGH, AND THIS METHOD SHIPPED WITH
     * ONLY THOSE** (4450). `sns.` + `.amazonaws.com` admits
     * `https://sns.evil.s3.amazonaws.com/c.pem` — an **attacker-controlled S3
     * bucket**, which is the precise case AWS's own validator names in its
     * comment on this check: *"one belonging to AWS, and not just something
     * from the amazonaws domain, which could include S3 buckets."* It also
     * admits `sns.a.b.c.amazonaws.com`. The refusal dataset in
     * `SnsMessageVerifierTest` had no multi-label case at all, which is why the
     * hole read as covered.
     *
     * What is enforced now is AWS's `MessageValidator::validateUrl()` as
     * written (`aws/aws-php-sns-message-validator`, read 2026-08-16), both
     * halves:
     *
     *   - the host matches `/^sns\.[a-zA-Z0-9\-]{3,}\.amazonaws\.com(\.cn)?$/`,
     *     which is **one** label between `sns.` and the registrable domain, and
     *     the `(\.cn)?` is AWS China rather than decoration;
     *   - the URL ends in `.pem`. A certificate is a `.pem` and nothing else,
     *     and this is what stops an object *inside* a legitimate AWS-owned host
     *     being read as one.
     *
     * ⚠️ **THE SUFFIX TEST IS ON THE WHOLE URL, NOT THE PATH**, exactly as
     * AWS's `substr($url, -4)` is — so a query string after the `.pem` refuses.
     * SNS does not append one; matching AWS's own behaviour is worth more than
     * a tolerance nobody can point at a case for.
     *
     * ⚠️ **AN EMPTY PATTERN REFUSES**, which is the same fail-closed reading as
     * an empty topic allowlist: a misconfigured deployment leaves this endpoint
     * inert rather than open, and `preg_match('')` would otherwise error and
     * take the request with it.
     */
    private function certificateUrlIsAllowed(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if ($scheme !== 'https' || ! is_string($host)) {
            return false;
        }

        $suffix = (string) config('platform_mail.sns.certificate_url_suffix');

        if ($suffix === '' || ! str_ends_with($url, $suffix)) {
            return false;
        }

        $pattern = (string) config('platform_mail.sns.certificate_host_pattern');

        return $pattern !== '' && preg_match($pattern, mb_strtolower($host)) === 1;
    }

    /**
     * The signing certificate — or which kind of "no" this was.
     *
     * ⚠️ **THREE RETURNS AND THEY ARE THREE DIFFERENT FACTS** (9380–9394), on
     * `TurnstileVerifier`'s pattern, which is the in-tree shape for a fetch that
     * can fail in more than one way. A `string` is the certificate.
     * {@see WebhookVerification::KeysUnavailable} is *"AWS answered and it was
     * their own server erroring"*. `null` is every other refusal, and the class
     * docblock says at length why a connection failure is among them here and
     * is not in the sibling verifier.
     *
     * ⛔ **THE `catch` IS NOT DEFENSIVE TIDYING.** Without it this method throws
     * out of a `POST /webhooks/ses` request whenever AWS's certificate host does
     * not answer, and nothing between here and the framework catches it — a 500
     * from a public endpoint, with no counter incremented and nothing naming the
     * cause.
     *
     * ⚠️ **`VendorLog::failure()` ON EVERY ARM, AND THIS FILE PREVIOUSLY LOGGED
     * NOTHING AT ALL.** The reason is the counter's blind spot rather than the
     * counter: `PlatformHealthSignal::WebhookKeyUnavailable` records *that* the
     * fetch failed for an endpoint and deliberately carries no cause, so the
     * distinction between "did not resolve" and "AWS answered 503" lives in the
     * log where an operator can read it after the bell. ⛔ **The reason string
     * is a fixed label or a status code and never a vendor message** — a Guzzle
     * connection exception's message carries the full request URI, which is
     * `VendorLog::failure()`'s own stated rule.
     */
    private function certificate(string $url): string|WebhookVerification|null
    {
        $cached = Cache::get('platform-mail:sns:cert:'.sha1($url));

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::timeout((int) config('platform_mail.sns.certificate_timeout', 5))->get($url);
        } catch (ConnectionException) {
            VendorLog::failure('aws-sns', 'GET', $url, ConnectionException::class);

            // The residual the class docblock names: a sender chooses this URL,
            // so a host that does not resolve is as likely to be theirs as ours.
            return null;
        }

        if ($response->failed()) {
            VendorLog::failure('aws-sns', 'GET', $url, 'http_'.$response->status());

            // ⛔ **`>= 500` AND NOT `failed()`.** A 4xx on a host constrained to
            // AWS is the sender having named an object that is not there; a 5xx
            // is AWS's own server, which no sender can arrange.
            return $response->status() >= 500
                ? WebhookVerification::KeysUnavailable
                : null;
        }

        $body = $response->body();

        if (! str_contains($body, 'BEGIN CERTIFICATE')) {
            VendorLog::failure('aws-sns', 'GET', $url, 'not_a_certificate');

            return null;
        }

        Cache::put('platform-mail:sns:cert:'.sha1($url), $body, $this->certificateTtlSeconds());

        return $body;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function stringToSign(array $message): ?string
    {
        $type = $message['Type'] ?? null;

        $fields = null;
        if ($type === 'Notification') {
            $fields = ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'];
        } elseif ($type === 'SubscriptionConfirmation' || $type === 'UnsubscribeConfirmation') {
            $fields = ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
        }

        if ($fields === null) {
            return null;
        }

        $string = '';

        foreach ($fields as $field) {
            $value = $message[$field] ?? null;

            // `Subject` is optional and is omitted entirely when absent —
            // including it as an empty string breaks every signature.
            if (! is_string($value)) {
                if ($field === 'Subject') {
                    continue;
                }

                return null;
            }

            $string .= $field."\n".$value."\n";
        }

        return $string;
    }
}

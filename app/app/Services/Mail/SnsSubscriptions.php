<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Console\Commands\ConfirmSnsSubscription;
use App\Enums\SnsConfirmationOutcome;
use App\Enums\WebhookVerification;
use App\Http\Controllers\Mail\SesFeedbackController;
use App\Services\Config\DefaultsRegistry;
use App\Support\VendorLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The door handle on this side of `POST /webhooks/ses` (10220–10229).
 *
 * ⛔ **THE SAFE HALF WAS BUILT AND THE HUMAN HALF WAS ASSUMED.**
 * {@see SesFeedbackController} refuses to confirm an
 * SNS subscription for itself and says a person does it instead — *"one console
 * click, once"*. **There is no such click.** The SNS console's *Confirm
 * subscription* action asks for the **token**, and the token exists nowhere but
 * inside the POST body that controller received and deliberately did not log.
 * Measured on production on 2026-08-26: a genuine, signature-verified
 * confirmation arrived, was logged with its topic named, and left the
 * subscription at `Pending confirmation` with **no supported way to complete
 * it at all**.
 *
 * ⚠️ **WHAT THIS CLASS IS NOT.** It is not an auto-confirm and must not become
 * one. The controller stores what arrived and fetches nothing; the fetch
 * happens only when a person runs {@see ConfirmSnsSubscription}
 * and names the topic. `SesFeedbackEndpointTest`'s *a subscription confirmation
 * is never auto-confirmed* is the assertion that keeps the two apart, and it is
 * unchanged.
 *
 * ## Why the allowlist — and not this refusal — is what closes the original threat
 *
 * ⚠️ **MEASURED BY MUTATION RATHER THAN READ (10220).** The controller's
 * docblock argues the refusal on the ground that *"anybody who can reach this
 * URL can subscribe it to their own topic"*. That is no longer the state of the
 * code and has not been since the topic allowlist landed:
 * {@see SnsMessageVerifier::verify()} asks `topicIsAllowed()` **before** it
 * looks at `Type`, so a `SubscriptionConfirmation` carrying a stranger's
 * `TopicArn` is refused with a `401` and never reaches the branch at all. A
 * stranger's topic ARN carries **their** AWS account id and can never match a
 * value we put in `SES_SNS_TOPIC_ARNS`, and `TopicArn` is one of the fields
 * AWS's own signature covers, so it cannot be edited after the fact either.
 *
 * ⛔ **SO WHAT SURVIVES OF THE REFUSAL IS A DIFFERENT ARGUMENT FROM THE ONE
 * WRITTEN DOWN, AND IT IS KEPT ON PURPOSE.** *"An operator should know a
 * subscription happened"* is not *"an attacker could subscribe us"*: a
 * confirmation for a topic we do allow means somebody with access to our own
 * AWS account pointed a feed at this endpoint, and a person completing it by
 * hand is the only moment anybody notices. **Widening this to an auto-confirm
 * is a behaviour change on the endpoint that can suppress any address on the
 * platform, and it is the owner's to approve — it is recommended in 10222 and
 * deliberately not adopted here.**
 *
 * ## Where the capability lives, for how long, and what removes it
 *
 * ⛔ **A `SubscribeURL` IS A SINGLE-USE CAPABILITY** — whoever fetches it
 * confirms the subscription — which is why the controller refuses to write one
 * to a log file, and that refusal stands. What replaces the log is a store with
 * three properties a log has none of: it **expires** on its own after
 * {@see self::PENDING_TTL_SECONDS}, it is **removed** the moment it is spent,
 * and it is **keyed by a topic this deployment already accepts**, so a value
 * for any other topic is unreachable by construction.
 *
 * ⚠️ **`CACHE_STORE` IS `database` ON PRODUCTION, SO "THE CACHE" IS A TABLE.**
 * The row is platform-scoped and carries no tenant, like the certificate cache
 * one method away in {@see SnsMessageVerifier}; it holds a URL AWS minted for
 * our own topic and no personal data of any kind. **Anybody who can read that
 * row can already read `opt_outs`**, which is the thing the capability could be
 * used to write to — so the store adds no reader that did not already have the
 * outcome.
 *
 * ⚠️ **ONE HOUR, AND IT IS SHORTER THAN AWS'S OWN TOKEN LIFETIME ON PURPOSE.**
 * The subscription is created by a person at a console who is waiting for it;
 * an hour covers that and does not cover coming back tomorrow. Coming back
 * tomorrow costs one *Request confirmation* click at AWS, which is cheap — and
 * the alternative is a live capability sitting in a table for three days for
 * the convenience of not clicking it.
 */
final class SnsSubscriptions
{
    /**
     * How long a pending confirmation is held before it is dropped.
     *
     * See the class docblock: deliberately shorter than the token's own life at
     * AWS, because the expensive direction is a capability that outlives the
     * person who caused it.
     */
    public const int PENDING_TTL_SECONDS = 3600;

    public static function pendingTtlSeconds(): int
    {
        return app(DefaultsRegistry::class)->int('mail.sns.pending_ttl_seconds');
    }

    /**
     * ⚠️ **LONGER THAN THE CERTIFICATE FETCH's FIVE SECONDS, BECAUSE THE TWO
     * FETCHES SIT IN DIFFERENT PLACES.** That one is on the hot path of an
     * inbound webhook during a bounce storm and must give up quickly; this one
     * is an operator at a terminal, once, watching the output.
     */
    public const int CONFIRM_TIMEOUT_SECONDS = 10;

    public function confirmTimeoutSeconds(): int
    {
        return app(DefaultsRegistry::class)->int('mail.sns.confirm_timeout_seconds');
    }

    /**
     * Hold what arrived, so that a person can act on it later.
     *
     * ⚠️ **CALLED ONLY AFTER {@see WebhookVerification::Verified}**, so the URL
     * is one AWS itself minted and signed. The host is nevertheless
     * checked here, and again in {@see self::confirm()}, because a stored value
     * is read by a different process at a later time: the check at the door
     * keeps a bad value out of the table, and the check at the fetch is the one
     * that decides where a socket opens.
     *
     * Answers whether it was held, so a caller can say something different when
     * it was not.
     */
    public function record(string $topicArn, string $subscribeUrl): bool
    {
        if (! $this->isSnsUrl($subscribeUrl)) {
            // ⚠️ NEITHER THE URL NOR ANY PART OF IT IS LOGGED, HERE OR ANYWHERE
            // ELSE IN THIS CLASS — that is the controller's rule and it does not
            // stop applying because the value turned out to be malformed.
            Log::warning('An SNS subscription confirmation named a URL that is not an SNS host and was not held.', [
                'topic_arn' => $topicArn,
            ]);

            return false;
        }

        Cache::put(
            self::key($topicArn),
            ['url' => $subscribeUrl, 'arrived_at' => CarbonImmutable::now()->toIso8601String()],
            self::pendingTtlSeconds(),
        );

        return true;
    }

    /**
     * Every confirmation this deployment is currently holding.
     *
     * ⚠️ **THE ALLOWLIST IS THE ENUMERATION, AND THAT IS WHY THERE IS NO INDEX
     * TABLE.** A cache cannot be listed, and building a second table to list it
     * would be a second place for a capability to live. The set of topics this
     * endpoint accepts is already written down in `SES_SNS_TOPIC_ARNS`, it is
     * small, and it is exactly the set a pending confirmation can belong to —
     * so the lookup is one `Cache::get()` per allowed topic and a value for any
     * other topic is unreachable rather than merely unlisted.
     *
     * ⚠️ **A CONSEQUENCE, STATED RATHER THAN DISCOVERED**: editing the allowlist
     * between the arrival and the confirmation makes a held value invisible.
     * It expires on its own, and the remedy is the same *Request confirmation*
     * click every other empty answer here points at.
     *
     * @return array<string, array{url: string, arrived_at: CarbonImmutable}>
     */
    public function pending(): array
    {
        $held = [];

        foreach (self::allowedTopics() as $topicArn) {
            $value = Cache::get(self::key($topicArn));

            if (! is_array($value) || ! is_string($value['url'] ?? null)) {
                continue;
            }

            $arrivedAt = $value['arrived_at'] ?? null;

            $held[$topicArn] = [
                'url' => $value['url'],
                'arrived_at' => is_string($arrivedAt)
                    ? CarbonImmutable::parse($arrivedAt)
                    : CarbonImmutable::now(),
            ];
        }

        return $held;
    }

    /**
     * Complete the subscription for one topic — the act a person performs.
     *
     * ⛔ **THE RESPONSE BODY IS NEITHER PARSED NOR LOGGED.** AWS answers
     * `ConfirmSubscription` with an XML document naming the new subscription,
     * and this application has never seen one: writing a check against a shape
     * read only in documentation is precisely the defect
     * {@see SnsMessageVerifier}'s docblock spends a paragraph on. **A 2xx is
     * the judgement.** And the body is kept out of the log for the reason the
     * URL is — an error document from this endpoint can echo the token back.
     *
     * ⚠️ **REDIRECTS ARE REFUSED.** The host is checked before the fetch, and a
     * followed redirect is how that check is bypassed after it has passed — and
     * the thing that would travel to the redirect target is the token. SNS does
     * not redirect, so nothing correct is lost.
     *
     * ⚠️ **`withoutRedirecting()` IS NOT OBSERVABLE UNDER `Http::fake()`**,
     * which hands the stubbed response back without Guzzle's redirect
     * middleware, so no test in this repository proves it. **What is proven is
     * the half that makes it safe anyway**: the status check below is `2xx` and
     * not `! failed()`, so an un-followed `302` is refused rather than reported
     * as a confirmed subscription.
     */
    public function confirm(string $topicArn): SnsConfirmationOutcome
    {
        if (! in_array($topicArn, self::allowedTopics(), true)) {
            return SnsConfirmationOutcome::TopicNotAllowed;
        }

        $held = $this->pending()[$topicArn] ?? null;

        if ($held === null) {
            return SnsConfirmationOutcome::NothingPending;
        }

        if (! $this->isSnsUrl($held['url'])) {
            // The store is a trust boundary in both directions. A value that
            // could not be written today can still be sitting in the table from
            // before a pattern was tightened.
            Cache::forget(self::key($topicArn));

            return SnsConfirmationOutcome::TopicNotAllowed;
        }

        // ⚠️ **SCHEME AND HOST ONLY, ASSEMBLED HERE RATHER THAN HANDED THE WHOLE
        // URL.** {@see VendorLog::endpoint()} already drops a query string, and
        // relying on that would make the token's absence from the log a
        // property of another class's implementation rather than of this call.
        $endpoint = 'https://'.(string) parse_url($held['url'], PHP_URL_HOST).'/';

        try {
            $response = Http::timeout($this->confirmTimeoutSeconds())
                ->withoutRedirecting()
                ->get($held['url']);
        } catch (ConnectionException) {
            VendorLog::failure('aws-sns', 'GET', $endpoint, ConnectionException::class);

            // Nothing was presented, so nothing was spent. The held value stays.
            return SnsConfirmationOutcome::Unreachable;
        }

        // Spent either way from here: a token AWS has answered about is not
        // going to start working, and one it accepted is finished.
        Cache::forget(self::key($topicArn));

        // ⛔ **`successful()` AND NOT `! failed()`, WHICH ARE NOT THE SAME
        // PREDICATE.** `failed()` is `clientError() || serverError()`, so a
        // **3xx is not a failure to it** — and this call deliberately does not
        // follow redirects, which means a redirect would arrive here as an
        // un-followed `302` and be reported to an operator as a confirmed
        // subscription. Driven red by the redirect test in
        // `SnsSubscriptionsTest`. A 2xx is the judgement, exactly as the
        // docblock above says.
        if (! $response->successful()) {
            VendorLog::failure('aws-sns', 'GET', $endpoint, 'http_'.$response->status());

            return SnsConfirmationOutcome::Refused;
        }

        // ⚠️ **THE ONE DURABLE RECORD OF A SENSITIVE ACT, AND IT IS WEAKER THAN
        // `audit_log` — SAID OUT LOUD RATHER THAN IMPLIED** (10226).
        // `AuditService::record()` calls `Tenancy::idOrFail()` because
        // `audit_log` is tenant-owned with RLS on `business_id`, and this
        // subscription belongs to no tenant — `SetStateMessagingRule` records
        // the identical constraint for a statute. `staff_events` does not fit
        // either: it is chokepointed to `StaffDirectory` and requires a subject
        // user, and a console command has none. So this is a log line, and the
        // gap is raised for the owner rather than papered over.
        Log::warning('An SNS subscription was confirmed by an operator. This endpoint can now suppress addresses on behalf of this topic.', [
            'topic_arn' => $topicArn,
        ]);

        return SnsConfirmationOutcome::Confirmed;
    }

    /**
     * Is this an Amazon SNS regional endpoint?
     *
     * ⚠️ **THE SAME PATTERN {@see SnsMessageVerifier} CONSTRAINS THE SIGNING
     * CERTIFICATE URL WITH, READ RATHER THAN RE-TYPED.** It describes an SNS
     * regional host, which is what both URLs have to be; a second copy of it is
     * how the second one quietly stops matching the first. **The `.pem` suffix
     * is deliberately not applied** — that is a fact about a certificate, and a
     * `ConfirmSubscription` URL is a query string.
     *
     * ⚠️ **AN EMPTY PATTERN REFUSES**, on the fail-closed reading the verifier
     * gives for the same value: a misconfigured deployment leaves this inert
     * rather than fetching whatever it was handed.
     */
    private function isSnsUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        $host = $parts['host'] ?? null;

        if (($parts['scheme'] ?? null) !== 'https' || ! is_string($host)) {
            return false;
        }

        $pattern = (string) config('platform_mail.sns.certificate_host_pattern');

        return $pattern !== '' && preg_match($pattern, mb_strtolower($host)) === 1;
    }

    /**
     * @return list<string>
     */
    private static function allowedTopics(): array
    {
        $allowed = config('platform_mail.sns.topic_arns');

        if (! is_array($allowed)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $arn): string => is_string($arn) ? $arn : '', $allowed),
            static fn (string $arn): bool => $arn !== '',
        ));
    }

    /**
     * ⚠️ **HASHED, LIKE THE CERTIFICATE CACHE ONE METHOD AWAY.** An ARN is not a
     * secret, but a cache key is a string somebody concatenates into other
     * things, and a fixed-length one cannot carry a colon into a store that
     * treats it as a separator.
     */
    private static function key(string $topicArn): string
    {
        return 'platform-mail:sns:pending:'.sha1($topicArn);
    }
}

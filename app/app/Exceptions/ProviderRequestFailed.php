<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\OauthProvider;
use App\Services\Oauth\TokenService;
use App\Services\Providers\ProviderClient;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * A provider API call did not succeed.
 *
 * Two flags rather than one, because "should I try again?" and "is this a quota
 * problem?" have different answers and different remedies:
 *
 *   retryable   transport failure, 5xx, or a throttle. The queue brings the job
 *               back with jittered backoff.
 *
 *   quota       429, Google's 403 with a rate-limit reason, or one of Meta's
 *               numeric throttle codes. Distinguished from other retryable
 *               failures because a quota wall is a *planning* signal, not an
 *               outage — it belongs on the health board and, for Google Business
 *               Profile, it is the expected steady state rather than an
 *               incident. New Cloud projects sit at 0 QPM until an access
 *               application is approved, so every GBP automation must be able to
 *               hit this and fall through to its handoff() path rather than
 *               fail.
 *
 * Carries the status and a short reason code. Never a response body: provider
 * error bodies carry personal data and echo request parameters, and this message
 * reaches logs and error trackers.
 *
 * ## What this class does NOT do, so the next reader does not assume it
 *
 * It reports *whether to try again* and *whose fault the wall is*. It does not
 * split a permanent refusal into its causes — a revoked grant (401), a
 * connection that is healthy but may not read this resource (403), and a
 * resource that is gone (404) all arrive as `retryable = false` with their own
 * reason strings and nothing that branches on them. That is deliberate: the two
 * flags have readers ({@see ProviderClient::send()} and
 * the retired direct Google Business client), and a
 * third flag with no reader is the shape this codebase has recorded fourteen
 * times. The reconnect prompt is raised from the *refresh* path in
 * {@see TokenService}, not from a 401 here.
 *
 * {@see SearchConsoleRequestFailed} does make that four-way split, because its
 * one caller renders a different sentence per cause. **That is now the whole of
 * why it exists** — it was also written because the reason lookup below could
 * not read Search Console's envelope, and that half is fixed here.
 */
final class ProviderRequestFailed extends RuntimeException
{
    private function __construct(
        public readonly OauthProvider $provider,
        public readonly int $status,
        public readonly string $reason,
        public readonly bool $retryable,
        public readonly bool $quota,
    ) {
        parent::__construct("{$provider->value} request failed ({$status}): {$reason}");
    }

    /**
     * Google's rate-limit reasons, in both of the envelopes Google ships.
     *
     * `RESOURCE_EXHAUSTED` is the google.rpc.Status envelope the Business
     * Profile APIs return; the four camelCase tokens are the classic envelope's,
     * read from https://developers.google.com/webmaster-tools/v1/errors on
     * 2026-08-06, where all four are listed under 403.
     */
    private const GOOGLE_QUOTA_REASONS = [
        'RESOURCE_EXHAUSTED',
        'quotaExceeded',
        'rateLimitExceeded',
        'userRateLimitExceeded',
        'dailyLimitExceeded',
    ];

    /**
     * Meta's throttle codes, read from the Graph API rate-limiting guide on
     * 2026-08-06.
     *
     * 4 app-level · 17 user-level · 32 Pages API · 341 application limit ·
     * 613 custom limit. The business-use-case range 80000–80014 is handled
     * separately because it is a range with gaps.
     */
    private const META_THROTTLE_CODES = [4, 17, 32, 341, 613];

    /**
     * Classify a response the vendor actually returned.
     */
    public static function from(OauthProvider $provider, Response $response): self
    {
        $status = $response->status();
        $reason = self::reasonIn($response);
        $quota = self::isQuota($provider, $status, $reason);

        return new self(
            $provider,
            $status,
            $reason,
            $quota || $status >= 500,
            $quota,
        );
    }

    /**
     * The vendor's own machine-readable reason token, or a synthetic one.
     *
     * ⚠️ **THE ORDER IS LOAD-BEARING AND `error.errors.0.reason` HAS TO BE
     * FIRST.** Three of the vendors this class serves ship three different
     * envelopes, and two of them put something at `error.code`:
     *
     *   classic Google   {"error":{"errors":[{"reason":"quotaExceeded"}],
     *                     "code":403,"message":"…"}}
     *                    `code` is the numeric HTTP status. The discriminator is
     *                    inside `errors[]`, and Search Console, the Webmaster v3
     *                    endpoints and the older googleapis surfaces all use it.
     *   google.rpc       {"error":{"code":429,"status":"RESOURCE_EXHAUSTED"}}
     *                    `status` is the discriminator. Business Profile.
     *   Microsoft Graph  {"error":{"code":"activityLimitReached","message":"…"}}
     *                    `code` is a *string*.
     *   Meta             {"error":{"code":4,"type":"OAuthException", …}}
     *                    `code` is a numeric Meta code, finer-grained than
     *                    `type`, so it is preferred over it.
     *
     * Reading `error.status ?? error.code ?? error.type`, which is what this did
     * until 2026-08-06, reduced *every* classic-envelope failure to the string
     * `"403"`: a `quotaExceeded` became a permanent denial that was never
     * retried, and an `insufficientPermissions` was retried three times and then
     * abandoned under the same label. Recorded at decision 1088 and fixed here.
     *
     * A numeric `error.code` equal to the HTTP status is not a reason — it is
     * the status written twice — so it is skipped rather than returned. That is
     * the rule that makes the classic envelope fall through to `http_403`
     * instead of `403` when it arrives with no `errors[]` array at all.
     */
    private static function reasonIn(Response $response): string
    {
        $status = $response->status();

        $classic = $response->json('error.errors.0.reason');

        if (is_string($classic) && $classic !== '') {
            return $classic;
        }

        $rpc = $response->json('error.status');

        if (is_string($rpc) && $rpc !== '') {
            return $rpc;
        }

        $code = $response->json('error.code');

        if (is_string($code) && $code !== '') {
            return $code;
        }

        if (is_int($code) && $code !== $status) {
            return (string) $code;
        }

        $type = $response->json('error.type');

        if (is_string($type) && $type !== '') {
            return $type;
        }

        return 'http_'.$status;
    }

    /**
     * Is this the vendor telling us to slow down, rather than to stop?
     *
     * 403 is ambiguous everywhere. Google returns it both for "you have no
     * quota" and for "you are not allowed", and the reason string is the only
     * way to tell — getting it wrong means either retrying a permanent denial
     * forever or giving up on a temporary wall. Decision 532 recorded the same
     * ambiguity costing an afternoon on Zernio.
     *
     * ⚠️ Meta's throttles are matched on the **code and the provider**, never on
     * the HTTP status, because Meta does not document a status for them and
     * ships them as 4xx of more than one flavour. The provider gate is what
     * stops a bare `"4"` out of some future vendor's envelope reading as a
     * throttle.
     *
     * Microsoft Graph needs no clause of its own: it documents 429 as its
     * throttle and that is caught below. Its 509 "Bandwidth Limit Exceeded" is
     * also a throttle, and is deliberately left as an ordinary retryable 5xx —
     * it is already retried, so the only thing a `quota` flag would change is
     * the word on the health board, and no reader distinguishes them for
     * Microsoft.
     */
    private static function isQuota(OauthProvider $provider, int $status, string $reason): bool
    {
        if ($status === 429) {
            return true;
        }

        if ($status === 403 && in_array($reason, self::GOOGLE_QUOTA_REASONS, true)) {
            return true;
        }

        return self::isMetaThrottle($provider, $reason);
    }

    private static function isMetaThrottle(OauthProvider $provider, string $reason): bool
    {
        $isMeta = in_array($provider, [
            OauthProvider::Facebook,
            OauthProvider::Instagram,
            OauthProvider::MetaAds,
        ], true);

        if (! $isMeta || ! ctype_digit($reason)) {
            return false;
        }

        $code = (int) $reason;

        return in_array($code, self::META_THROTTLE_CODES, true)
            || ($code >= 80000 && $code <= 80014);
    }

    /**
     * The call never reached the vendor at all.
     *
     * Always retryable. `29`'s rule about never trusting a vendor's uptime cuts
     * both ways: assume they will be down, and assume they will come back.
     */
    public static function unreachable(OauthProvider $provider, string $reason): self
    {
        return new self($provider, 0, $reason, true, false);
    }
}

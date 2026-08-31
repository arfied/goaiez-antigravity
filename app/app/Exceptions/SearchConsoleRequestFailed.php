<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Services\Gsc\DailyMetrics;
use App\Services\Gsc\GoogleSearchConsoleClient;
use App\Services\Oauth\TokenService;
use App\Services\Visibility\VisibilityReading;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * A Search Console call did not succeed, classified into the four things that
 * failure can actually mean.
 *
 * ⚠️ **WHY THIS EXISTS RATHER THAN `ProviderRequestFailed`, AND HALF OF THE
 * ORIGINAL ANSWER NO LONGER APPLIES.** Two reasons were given when this class
 * shipped (decision 1088). Only the second survives:
 *
 *   ~~the reason lookup~~  `ProviderRequestFailed::from()` read the reason out
 *                          of `error.status`, `error.code` or `error.type`, and
 *                          Search Console returns the classic Google envelope
 *                          where `error.code` is the **numeric HTTP status** and
 *                          the discriminator lives at `error.errors[0].reason`.
 *                          It therefore reduced every failure here to `"403"`.
 *                          ✅ **Fixed 2026-08-06** — that classifier now reads
 *                          `error.errors.0.reason` first and knows all four of
 *                          the classic quota reasons.
 *
 *   the four-way split     Still true, and it is now the whole reason. That
 *                          class reports *retry or not* and *whose wall it is*,
 *                          which is what its two readers branch on. This one
 *                          separates a revoked grant from a forbidden property
 *                          from a missing one, because {@see readingReason()}
 *                          renders a different sentence — and a different
 *                          remedy, owned by a different person — for each.
 *
 * So this class is not a workaround any more; it is a richer classification for
 * the one caller that renders it. ⚠️ **Do not fold it back in by adding
 * `revoked` and `forbidden` flags to the shared class** — nothing outside this
 * file branches on them, and a field with no reader is the shape this codebase
 * has recorded fourteen times.
 *
 * ## The four meanings, read from the live error reference
 *
 * https://developers.google.com/webmaster-tools/v1/errors, read 2026-08-05:
 *
 *   401 `authError`               "The authorization credentials provided for the
 *                                 request are invalid." Also `expired`. **The
 *                                 tenant's grant is gone** — the owner must
 *                                 reconnect, and nobody else can fix it.
 *   403 `insufficientPermissions` "The authenticated user does not have
 *                                 sufficient permissions to execute this
 *                                 request." **The connected Google account can
 *                                 see this property and cannot read it** — a
 *                                 restricted or unverified role. The connection
 *                                 is healthy; the remedy is inside Search
 *                                 Console, not here.
 *   403 `quotaExceeded`           "The requested operation requires more
 *                                 resources than the quota allows." **Ours.**
 *                                 Retryable, and it is a planning signal rather
 *                                 than an incident.
 *   404 `notFound`                "a resource associated with the request could
 *                                 not be found." **The property is gone**, or the
 *                                 stored `site_url` never belonged to this
 *                                 account.
 *
 * The three outcomes have three different owners — the tenant, the tenant's
 * Google settings, and us — and sending an owner to re-authorise a connection
 * that is fine while the real fix is an unread invoice is the failure decision
 * 532 recorded costing an afternoon.
 *
 * ## The fifth meaning, which had no owner at all until 2026-08-22
 *
 * ⛔ **A 5xx IS GOOGLE ANSWERING ABOUT GOOGLE, AND IT WAS BEING FILED AS A FACT
 * ABOUT THE TENANT** (7400). The same page documents 500 `internalError` and
 * three 503 reasons — `backendError`, `notReady`, and `backendNotConnected`,
 * *"The request failed due to a connection error"* — read again on
 * **2026-08-22**. All four fall to {@see readingReason()}'s `'vendor_error'`,
 * which is right, and the health write beside the throw re-derived its own
 * answer from `quota` alone and called everything else `'error'`. So an outage
 * at Google's end wrote `token_status = 'error'` against the customer's OAuth
 * grant, on every tenant that happened to sync during it.
 *
 * **The fifth owner is Google, and the honest remedy was to stop claiming an
 * owner we do not have** — {@see TokenService::recordCallFailure()}
 * carries the rule. Retry is untouched: `retryable` is still `$quota || $status
 * >= 500` and `SyncSearchConsoleJob::classify()` still hands a 5xx back to the
 * queue.
 *
 * ⚠️ **AND ONE CLASSIFICATION GAP IS RAISED HERE RATHER THAN CLOSED** (7400).
 * The same error page lists more 403 rate-limit reasons than the three below:
 * `dailyLimitExceeded`, `limitExceeded`, `concurrentLimitExceeded`,
 * `servingLimitExceeded`, `rateLimitExceededUnreg`, `userRateLimitExceededUnreg`,
 * `variableTermLimitExceeded` and `variableTermExpiredDailyExceeded`. Today each
 * of them lands as `forbidden`, and **`ProviderRequestFailed` already counts
 * `dailyLimitExceeded` as quota**, so the two classifiers disagree about one
 * documented Google reason. ⛔ **Not fixed here because moving a reason into
 * `quota` moves it into `retryable`**, and 7265 pins retry behaviour for this
 * slice. It is a ruling with a live consequence — a daily quota wall currently
 * reads to an owner as *"this account cannot see that property"*.
 *
 * ## What never travels
 *
 * The status and a short reason code only. Google's error bodies carry `message`
 * strings that echo request parameters — for this API that means the site
 * property, which names the tenant's domain — and this message reaches logs and
 * error trackers.
 */
final class SearchConsoleRequestFailed extends RuntimeException
{
    private function __construct(
        public readonly int $status,
        public readonly string $reason,
        /** The tenant's OAuth grant is gone. Only the owner can fix it. */
        public readonly bool $revoked,
        /** The account is connected but may not read this property. */
        public readonly bool $forbidden,
        /** Our quota, not theirs. */
        public readonly bool $quota,
        /** Worth trying again later. */
        public readonly bool $retryable,
    ) {
        parent::__construct("search console request failed ({$status}): {$reason}");
    }

    /**
     * Classify a response Google actually returned.
     */
    public static function from(Response $response): self
    {
        $status = $response->status();
        $reason = self::reasonIn($response);

        // ⚠️ 403 IS SPLIT ON THE REASON STRING AND FALLS BACK TO `forbidden`, NOT
        // TO `quota`. An unreadable 403 body treated as a quota wall would be
        // retried three times and then reported to the owner as our capacity
        // problem; treated as forbidden it surfaces as "this account cannot read
        // that property", which is the answer a human can check in one click.
        // Fail towards the diagnosis somebody can verify.
        $quota = $status === 429
            || ($status === 403 && in_array($reason, ['quotaExceeded', 'rateLimitExceeded', 'userRateLimitExceeded'], true));

        $revoked = $status === 401;

        $forbidden = $status === 403 && ! $quota;

        return new self(
            status: $status,
            reason: $reason,
            revoked: $revoked,
            forbidden: $forbidden,
            quota: $quota,
            // 404 is deliberately NOT retryable: a property that is not there
            // will not be there in five minutes, and retrying spends quota to
            // re-learn a fact the owner has to act on.
            retryable: $quota || $status >= 500,
        );
    }

    /**
     * The call never reached Google.
     */
    public static function unreachable(string $reason): self
    {
        return new self(0, $reason, false, false, false, true);
    }

    /**
     * The call reached Google, Google answered `2xx`, and the body it sent back
     * did not carry a metric this vendor never legitimately omits.
     *
     * ⛔ **A FIFTH SHAPE OF FAILURE, DISTINCT FROM ALL FOUR ABOVE.** Those four
     * classify a status code Google actually returned as a refusal; this one
     * exists because {@see GoogleSearchConsoleClient::pageImpressions()}
     * found a row with a page key but no readable `impressions` value —
     * something the classic Search Console API's own reference never documents
     * happening for a row that exists at all (see
     * {@see DailyMetrics} for the same finding on
     * `position`). **Treated the same as an absent answer rather than turned
     * into a fabricated zero**, because a page with no row Google chose not to
     * send is a fact ("nobody saw it"); a page whose row arrived with a hole in
     * it is a fact about the response we cannot trust, and the two must not
     * collapse into the one number `28` §5.3.4 forbids implying.
     *
     * ⚠️ **`revoked`/`forbidden`/`quota` ARE ALL FALSE AND `status` IS `0`,
     * EXACTLY LIKE {@see self::unreachable()}** — this is not a claim about the
     * tenant's grant or about our budget, and health is left untouched because
     * the HTTP round trip to Google's own server succeeded.
     * `readingReason()` falls through its `default` arm for it, same as a 5xx.
     */
    public static function malformedResponse(): self
    {
        return new self(0, 'malformed_response', false, false, false, true);
    }

    /**
     * The short code that reaches {@see VisibilityReading}.
     *
     * One string per remedy, and the mapping is here rather than at the call
     * site so two callers cannot disagree about what a 403 means.
     */
    public function readingReason(): string
    {
        return match (true) {
            $this->revoked => 'connection_revoked',
            $this->quota => 'quota_exhausted',
            $this->forbidden => 'property_forbidden',
            $this->status === 404 => 'property_missing',
            default => 'vendor_error',
        };
    }

    /**
     * Google's own reason token, or a synthetic one.
     *
     * ⚠️ Reads `error.errors.0.reason` FIRST. `error.status` and `error.code` are
     * both present in this envelope and neither discriminates — `code` is the
     * numeric status and `status` is absent on the v3 endpoints. Reading them
     * first produces `"403"` for both of the 403s this class exists to tell
     * apart; `ProviderRequestFailed::reasonIn()` made exactly that mistake until
     * 2026-08-06 and now shares this order.
     */
    private static function reasonIn(Response $response): string
    {
        $reason = $response->json('error.errors.0.reason');

        if (is_string($reason) && $reason !== '') {
            return $reason;
        }

        $status = $response->json('error.status');

        if (is_string($status) && $status !== '') {
            return $status;
        }

        return 'http_'.$response->status();
    }
}

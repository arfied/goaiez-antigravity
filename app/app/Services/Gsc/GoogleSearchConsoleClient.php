<?php

declare(strict_types=1);

namespace App\Services\Gsc;

use App\Contracts\SearchConsoleClient;
use App\Enums\GscPermissionLevel;
use App\Enums\OauthProvider;
use App\Exceptions\ProviderNotConnected;
use App\Exceptions\SearchConsoleRequestFailed;
use App\Exceptions\TokenRefreshFailed;
use App\Models\Business;
use App\Services\Content\ContentSelfAudit;
use App\Services\Oauth\TokenService;
use App\Support\VendorLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Google Search Console, read with the tenant's own OAuth grant.
 *
 * Endpoints, request bodies, response shapes, error reasons, scopes and quotas
 * read from Google's live documentation on **2026-08-05**, re-read in full on
 * **2026-08-21** (7269) and again on **2026-08-22** (7400) — both quota and
 * error pages stamped *"Last updated 2025-08-28 UTC"*, and nothing below has
 * moved — and cross-checked
 * against the machine-readable discovery document at
 * `https://searchconsole.googleapis.com/$discovery/rest?version=v1`, whose own
 * `revision` field reads **20260804**. Five things reading rather than
 * remembering caught, every one of which a from-memory implementation gets wrong:
 *
 *  1. ⚠️ **THE HOST IS NOT `www.googleapis.com`.** Every HTML reference page for
 *     this API still prints `https://www.googleapis.com/webmasters/v3/…`, and
 *     that is the URL a from-memory implementation writes. The live discovery
 *     document gives `rootUrl` as **`https://searchconsole.googleapis.com/`**
 *     with the same `webmasters/v3` path prefix. The legacy alias currently still
 *     answers, which is precisely what makes this dangerous: the wrong host works
 *     until it does not, and `www.googleapis.com` is a shared alias with its own
 *     deprecation history. The canonical host is used here, and it is a `const`
 *     rather than config for the reason `config/gbp.php` states — a host is a
 *     fact about somebody else's API, `40` Part 8's outbound lint reads literals,
 *     and an env-driven host is one an operator can point anywhere and one the
 *     subprocessor inventory can no longer verify.
 *
 *  2. ⚠️ **`metadata.firstIncompleteDate` IS CAMELCASE IN THE CONTRACT AND
 *     SNAKE_CASE IN THE REFERENCE PAGE.** Both are read; see
 *     {@see SearchAnalyticsResult}, where the whole freshness contract lives.
 *     Reading only the documented-in-prose spelling yields null forever and marks
 *     unfinished days as settled — decision 684's failure mode, with no symptom.
 *
 *  3. ⚠️ **`permissionLevel` ALSO HAS TWO DOCUMENTED SPELLINGS** — `siteOwner`
 *     versus `SITE_OWNER`. {@see GscPermissionLevel} accepts both, and
 *     the discovery document carries a fifth value
 *     (`SITE_PERMISSION_LEVEL_UNSPECIFIED`) the reference page does not mention.
 *
 *  4. **Every metric is a `double` on the wire, counts included.** See
 *     {@see DailyMetrics}.
 *
 *  5. **`rowLimit` is 1–25,000 with a default of 1,000.** A 28-day daily query
 *     returns at most 28 rows, so the default would do — but the default is
 *     Google's to change and this asks for what it needs.
 *
 * ## Quota, from https://developers.google.com/webmaster-tools/limits (2026-08-05)
 *
 * Search Analytics: **1,200 QPM per site**, **1,200 QPM per user**, and
 * **40,000 QPM / 30,000,000 QPD per project**. All other resources, which is
 * `sites.list`: **20 QPS and 200 QPM per user**, 100,000,000 QPD per project.
 * Exceeding any of them returns `quotaExceeded`.
 *
 * This slice makes **one Search Analytics call per location per day** and one
 * `sites.list` per connect flow, so the per-project ceiling is not a constraint
 * at any tenant count this business plans for — 30,000,000 QPD against one call
 * per location per day. The per-**user** limit is the one that could bite, since
 * it is scoped to the tenant's own Google account: a tenant with hundreds of
 * locations on one property would batch against 1,200 QPM. `config/gsc.php`
 * carries no rate governor for that reason and says so — a knob with no
 * reachable ceiling behind it is a knob nobody can tune correctly.
 *
 * ## Why this does not extend `ProviderClient`
 *
 * That base class is otherwise exactly right — it exists to guarantee every call
 * carries a tenant's vault token, which is true here. It is not used because its
 * classifier cannot express this API's failures: `ProviderRequestFailed::from()`
 * reads `error.status` / `error.code` / `error.type`, and Search Console's
 * discriminator is `error.errors[0].reason`. Against this endpoint it would
 * label both 403s `"403"` and mark neither as quota, collapsing decision 532's
 * two opposite meanings into one. {@see SearchConsoleRequestFailed} states the
 * full argument.
 *
 * What is kept from it deliberately: the vault as the only source of a
 * credential, `VendorLog` so the call shape is logged and the payload is not,
 * Laravel's HTTP client so `Http::fake()` and `preventStrayRequests()` both see
 * through it (decision 277 — an SDK's own Guzzle would be invisible to both and
 * to the outbound lint), and **no synchronous retry**: one attempt, a classified
 * failure, and retrying left to the job that has backoff and jitter.
 */
final class GoogleSearchConsoleClient implements SearchConsoleClient
{
    /**
     * The service host, from the discovery document's `rootUrl` plus the `v3`
     * path prefix its methods carry.
     *
     * ⚠️ Not `www.googleapis.com` — see point 1 of the class docblock.
     */
    private const string BASE = 'https://searchconsole.googleapis.com/webmasters/v3';

    /**
     * Google's documented maximum for `rowLimit`.
     *
     * Not a registry key, for the reason `ZernioGbpClient::MAX_LIMIT` gives: it
     * is a fact about somebody else's API, and a key here would let an operator
     * "raise" a third party's ceiling and get an error for the whole page.
     */
    private const int MAX_ROWS = 25_000;

    public function __construct(
        private readonly TokenService $tokens,
    ) {}

    public function properties(Business $business): array
    {
        $url = self::BASE.'/sites';

        $response = $this->send($business, 'GET', $url, fn (PendingRequest $request): Response => $request->get($url));

        $entries = $response->json('siteEntry');

        $properties = [];

        foreach (is_array($entries) ? $entries : [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $property = SiteProperty::fromApi($entry);

            if ($property !== null) {
                $properties[] = $property;
            }
        }

        return $properties;
    }

    public function dailyMetrics(
        Business $business,
        string $siteUrl,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): SearchAnalyticsResult {
        // rawurlencode, and the whole value. `sc-domain:example.com` needs its
        // colon encoded and `https://example.com/` needs its scheme separators
        // and its trailing slash encoded — the property key is one path segment,
        // not a URL being appended.
        $url = self::BASE.'/sites/'.rawurlencode($siteUrl).'/searchAnalytics/query';

        $body = [
            'startDate' => $start->format('Y-m-d'),
            'endDate' => $end->format('Y-m-d'),
            'dimensions' => ['date'],

            // Lowercase deliberately. The reference documents `all` / `final` as
            // case-insensitive in as many words; the discovery document declares
            // the proto enum as `ALL` / `FINAL`. The lowercase form is the one
            // whose acceptance is stated outright, so it is the one sent.
            //
            // ⚠️ `all` RATHER THAN `final`, AND THAT IS A DELIBERATE TRADE. See
            // SearchAnalyticsResult: `final` silently returns a shorter window
            // with no field saying so, which makes "Google has not finished
            // counting" indistinguishable from "traffic fell".
            'dataState' => 'all',

            // Web search only. Omitting it defaults to web today; naming it means
            // a change to that default is not a silent change to our numbers.
            'type' => 'web',

            // A daily query over any window this product asks for is far below
            // this. Asked for explicitly so the answer does not depend on
            // Google's default staying 1,000.
            'rowLimit' => self::MAX_ROWS,
        ];

        $response = $this->send(
            $business,
            'POST',
            $url,
            fn (PendingRequest $request): Response => $request->post($url, $body),
        );

        return SearchAnalyticsResult::fromResponse($response);
    }

    /**
     * Impressions per page — the daily self-audit's only external input.
     *
     * ⛔ **STILL `webmasters.readonly`, AND THAT IS THE WHOLE OF 1083 LEFT
     * ALONE.** The scope this client holds already covers `searchAnalytics.query`;
     * only the dimension changes. 5480's *"no future slice should look for the
     * submission code path"* is untouched — there is none here and none is
     * wanted, because `robots.txt` is one of Google's three documented submission
     * methods (`BUILD-PLAN` §2.11.5 conflict 2).
     *
     * ⚠️ **`dataState` IS `all` FOR {@see self::dailyMetrics()}'s REASON**, and
     * it matters more here rather than less: the audit's question is *"has this
     * page had zero impressions in ninety days"*, and `final` would silently
     * shorten the window with no field saying so — turning *"Google has not
     * finished counting the last three days"* into *"nobody has ever seen this
     * page"*.
     *
     * ⚠️ **A PAGE WITH NO ROW IS ABSENT RATHER THAN ZERO.** Google returns no
     * row for a page that got nothing, and the caller has the list of pages it
     * asked about — so absence is answered where the question was asked, not by
     * inventing a zero here.
     *
     * ⛔ **AND A ROW THAT DID COME BACK WITH NO READABLE `impressions` USED TO
     * BE THE SAME BUG ONE FIELD OVER.** `is_numeric($count) ? (int) $count : 0`
     * turned a malformed metric on a page Google *did* report on into the exact
     * same zero as a page it never mentioned — feeding
     * {@see ContentSelfAudit::hasSilentPages()}'s *"has
     * this page had zero impressions in ninety days"* a fabricated yes on a page
     * that, by the vendor's own documented behaviour, must have had at least one
     * impression to appear in the response at all (see
     * {@see DailyMetrics} for the same finding read against
     * the live Search Console documentation). That check auto-pauses a
     * location's content generation and alerts the owner. **Now throws**
     * {@see SearchConsoleRequestFailed::malformedResponse()} the moment any
     * returned row fails this test, which the audit's existing
     * `catch (SearchConsoleRequestFailed) { return false; }` already treats
     * exactly like an absent answer — no finding, no pause, on the strength of
     * data we cannot trust rather than a fabricated one we can.
     *
     * @return array<string, int>
     *
     * @throws SearchConsoleRequestFailed
     */
    public function pageImpressions(
        Business $business,
        string $siteUrl,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        $url = self::BASE.'/sites/'.rawurlencode($siteUrl).'/searchAnalytics/query';

        $body = [
            'startDate' => $start->format('Y-m-d'),
            'endDate' => $end->format('Y-m-d'),
            'dimensions' => ['page'],
            'dataState' => 'all',
            'type' => 'web',
            'rowLimit' => self::MAX_ROWS,
        ];

        $response = $this->send(
            $business,
            'POST',
            $url,
            fn (PendingRequest $request): Response => $request->post($url, $body),
        );

        $rows = $response->json('rows');

        $impressions = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $keys = $row['keys'] ?? null;

            if (! is_array($keys) || ! isset($keys[0]) || ! is_string($keys[0])) {
                continue;
            }

            $count = $row['impressions'] ?? null;

            if (! is_numeric($count)) {
                throw SearchConsoleRequestFailed::malformedResponse();
            }

            $impressions[$keys[0]] = (int) $count;
        }

        return $impressions;
    }

    /**
     * One attempt, logged, with any failure classified into our own type.
     *
     * ⛔ **A CONNECTION FAILURE IS OURS AND A RESPONSE IS THEIRS, AND ONLY THE
     * SECOND MAY REACH `provider_health`** (7261). Until 2026-08-21 the catch
     * below bound `$e`, used it for `$e::class` in a log line, and then called
     * `recordHealth(Gsc, 'error', 'connection')` — so **a DNS failure, a refused
     * connection or an egress firewall rule on our own box wrote
     * `token_status = 'error'` against the tenant's OAuth grant.** Every column
     * of that row is a sentence about the far end, and a `ConnectionException`
     * is precisely the outcome where the far end said nothing.
     *
     * ⚠️ **THE REMEDY IS SUBTRACTION RATHER THAN A BETTER STATUS.** A third
     * `token_status` meaning *"we could not reach them"* would be more accurate
     * and still wrong: it is a claim about **our** network filed under **their**
     * grant, and refining the vocabulary of a row nothing reads is 314–316's
     * shape with more words in it. The honest record of an unreachable vendor is
     * the `VendorLog` failure entry below — which is kept, is where an operator
     * looks, and does not name a tenant's connection as the thing that broke.
     *
     * ⚠️ **WHAT THE ROW SAYS INSTEAD IS TRUE AND MORE USEFUL.** `last_sync_at`
     * is written on success only, so a Google outage now leaves the row reading
     * *"active, last synced three days ago"* — which is exactly right, is
     * blameless about whose fault it is, and is the staleness signal `28` §9.8's
     * board wants. Overwriting `token_status` destroyed that reading and
     * clobbered `last_error` with `'connection'` on the way past.
     *
     * ⚠️ **THIS IS NOW THE SAME BEHAVIOUR AS `ProviderClient::send()`**, the
     * sibling on this code path, which always logged and threw without
     * recording health. The difference between the two was never argued in
     * either file; it is argued in both now, and
     * `tests/Feature/Architecture/VisibilityTest.php`'s *"a failure that never
     * reached the vendor is never recorded as a fact about the tenant"* is what
     * keeps it argued.
     *
     * ⛔ **THE OURS/THEIRS SPLIT IS NOT COLLAPSED — IT IS SHARPENED.** A 401 is
     * still theirs and still writes health, and `SyncSearchConsoleJob::classify()`
     * still turns it into the owner's Reconnect prompt. `unreachable()` still
     * carries `retryable: true`, so the queue's backoff ladder is untouched and
     * nothing about the retry behaviour changes here.
     *
     * ⛔ **THERE WAS A THIRD PARTY TO THAT SPLIT AND 7261 DID NOT SEE IT: A 503
     * IS THEIRS TOO, AND IT WAS BEING FILED AS THE TENANT'S** (7400). Google
     * documents three 503 reasons — `backendError`, `notReady` and
     * `backendNotConnected`, whose own wording is *"The request failed due to a
     * connection error"* — read from
     * https://developers.google.com/webmaster-tools/v1/errors on **2026-08-22**,
     * the page stamped *"Last updated 2025-08-28 UTC"*. **That is a connection
     * error at Google's end**, and the response branch below wrote it as
     * `token_status = 'error'` on the tenant's OAuth grant: the identical
     * category error 7261 had just removed from the catch above, one HTTP status
     * later, and consistent across both clients rather than an outlier — which
     * is what stopped anybody arguing it. **A 5xx no longer touches the light**;
     * see {@see TokenService::recordCallFailure()} for the whole rule.
     *
     * ⚠️ **THE LIBCURL ERRNO IS DELIBERATELY NOT READ, UNLIKE 7002/7062.** Those
     * slices classify a transport failure because a *write* may or may not have
     * landed at the vendor. Both calls this client makes are reads — `GET /sites`
     * and a `POST` that is a query — so nothing is at stake in telling errno 6
     * from errno 28 here, and the allowlist's own limit (a connect timeout is
     * indistinguishable from a read timeout, so 28 is excluded) would buy
     * nothing. Said rather than inherited silently.
     *
     * @param  callable(PendingRequest): Response  $call
     *
     * @throws SearchConsoleRequestFailed
     */
    private function send(Business $business, string $method, string $url, callable $call): Response
    {
        try {
            $response = VendorLog::timed(
                OauthProvider::Gsc->value,
                $method,
                $url,
                fn (): Response => $call($this->authorized($business)),
                $business->getKey(),
            );
        } catch (ConnectionException $e) {
            // Logged and thrown, and the health row is left exactly as the last
            // real answer from Google left it. See the docblock above.
            VendorLog::failure(OauthProvider::Gsc->value, $method, $url, $e::class, $business->getKey());

            throw SearchConsoleRequestFailed::unreachable('connection_failed');
        }

        if ($response->failed()) {
            $failure = SearchConsoleRequestFailed::from($response);

            // Health carries our own short reason code, never Google's body —
            // its `message` strings echo request parameters, which here means
            // the tenant's own domain.
            //
            // ⛔ **`$failure->quota ? 'quota_exhausted' : 'error'` IS WHAT THIS
            // SAID, AND IT MADE GOOGLE'S 503 THE CUSTOMER'S PROBLEM** (7400).
            // The status is now decided in one place for both clients.
            $this->tokens->recordCallFailure(
                OauthProvider::Gsc,
                $failure->status,
                $failure->quota,
                $failure->reason,
            );

            throw $failure;
        }

        $this->tokens->recordHealth(OauthProvider::Gsc, 'active', null, now());

        return $response;
    }

    /**
     * A request already carrying this tenant's bearer token.
     *
     * Fetched per call rather than held on the instance: the vault may refresh it
     * between two calls, and a cached instance property would send the old one.
     *
     * @throws ProviderNotConnected when there is no usable grant
     * @throws TokenRefreshFailed when Google's token endpoint
     *                            could not be reached
     */
    private function authorized(Business $business): PendingRequest
    {
        return Http::withToken($this->tokens->getValidToken($business, OauthProvider::Gsc))
            ->timeout((int) config('gsc.timeout', 15))
            ->acceptJson();
    }
}

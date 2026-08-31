<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\GscPermissionLevel;
use App\Exceptions\SearchConsoleRequestFailed;
use App\Models\Business;
use App\Services\Gsc\SearchAnalyticsResult;
use App\Services\Gsc\SiteProperty;
use Carbon\CarbonImmutable;

/**
 * Reading a tenant's Google Search Console data.
 *
 * WHY AN INTERFACE WITH ONE IMPLEMENTATION. Not for a future second vendor —
 * there is no second source for Google's own search data, and pretending
 * otherwise would be the "seam with an expiry date" that {@see GbpClient}'s
 * docblock had to correct. It exists so `SyncSearchConsoleJob` and the Ops
 * command can be driven in tests without a token, a network call or a quota, and
 * so the outbound host lives in exactly one file.
 *
 * ⚠️ **THIS IS READ-ONLY BY CONSTRUCTION, AND THAT IS DECISION 1083.** The scope
 * requested is `https://www.googleapis.com/auth/webmasters.readonly` — *"View
 * Search Console data for your verified sites"* — never the read-write
 * `.../auth/webmasters`, which additionally permits **submitting sitemaps and
 * adding or deleting properties** (both scopes verbatim from the live discovery
 * document, revision 20260804). There is no method here that writes, and there is
 * no grant behind it that could.
 *
 * ⚠️ **AND IT IS NOT TENANT-SCOPED BY ITSELF.** A `siteUrl` is opaque: nothing in
 * this client can tell whether a property belongs to the tenant it is being
 * called for, which is decision 531's finding about Zernio's `accountId` in a
 * different costume. Unlike that case the binding ships in the same slice —
 * `gsc_site_properties`, tenant-owned, RLS `ENABLE`+`FORCE`d — because this
 * client has a caller on day one and decision 1083 requires the store to land
 * with it rather than after it. **Callers pass a `siteUrl` that came from
 * `SearchConsoleProperties`, never one that came from a request.**
 */
interface SearchConsoleClient
{
    /**
     * Every site property the tenant's connected Google account can see.
     *
     * `GET webmasters/v3/sites`. Not paginated: the discovery document's
     * `SitesListResponse` has one field, `siteEntry`, and no page token.
     *
     * ⚠️ **Includes properties this account cannot read the data of.** Google
     * returns `siteUnverifiedUser` entries — a verification somebody started and
     * never finished — and they look exactly like readable ones in a picker.
     * {@see GscPermissionLevel::canReadPerformance()} is what tells
     * them apart, and choosing one is refused rather than stored, because a
     * property that can never answer renders as "no data yet" forever.
     *
     * @return list<SiteProperty>
     *
     * @throws SearchConsoleRequestFailed
     */
    public function properties(Business $business): array;

    /**
     * Daily clicks, impressions, CTR and average position for one property.
     *
     * `POST webmasters/v3/sites/{siteUrl}/searchAnalytics/query` with
     * `dimensions: ["date"]`.
     *
     * ⚠️ **DATES ARE PACIFIC TIME AND THE MOST RECENT DAYS ARE NOT SETTLED.** The
     * reference states the range is *"YYYY-MM-DD format in PT time"*, and the
     * result carries `firstIncompleteDate` so a caller can tell a finished day
     * from a moving one — see {@see SearchAnalyticsResult}, which is where the
     * freshness contract is documented in full. A caller that ignores it reports
     * a number that will change as though it were final.
     *
     * ⚠️ **AND THERE IS NO DATA OLDER THAN 16 MONTHS.** Google deletes it; no
     * request shape retrieves it. A backfill that asks for more gets an empty
     * window rather than an error, which reads as a business with no history.
     *
     * @param  string  $siteUrl  Exactly as `properties()` returned it. Encoded by
     *                           the implementation, never normalised.
     *
     * @throws SearchConsoleRequestFailed
     */
    public function dailyMetrics(
        Business $business,
        string $siteUrl,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): SearchAnalyticsResult;

    /**
     * Impressions per page over a window — doc `16` §15.3's self-audit input.
     *
     * ⛔ **THIS IS NOT A WIDENING OF THE OAUTH SCOPE AND MUST NOT BECOME ONE**
     * (1083, 5480). `webmasters.readonly` covers `searchAnalytics.query` in
     * full; what 1083 refused was the *write* scope, because it permits sitemap
     * submission **and property deletion**. This is the same read
     * {@see self::dailyMetrics()} already makes with a different dimension, and
     * no future slice should read it as a precedent for asking Google for more.
     *
     * ⚠️ **`page` RATHER THAN `date`, WHICH IS WHY IT IS A SECOND METHOD.** The
     * API returns one row per requested dimension tuple, and *"which of our
     * pages got nothing"* cannot be derived from a per-day total however it is
     * sliced.
     *
     * @return array<string, int> Page URL to impressions, absent when zero —
     *                            Google returns no row for a page nobody saw,
     *                            and inventing a zero row would make *"we have
     *                            no data"* and *"nobody saw it"* the same claim
     *                            (229).
     *
     * @throws SearchConsoleRequestFailed
     */
    public function pageImpressions(
        Business $business,
        string $siteUrl,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array;
}

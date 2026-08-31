<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Models\L2FactPageDaily;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * How much traffic one page of a tenant's own website got, from the pixel mart
 * — slice H's page-level half of *"measure 14–30 days, auto-rollback on
 * regression"* (`29` §2 rule 32).
 *
 * ⛔ **IT READS `l2_fact_page_daily` AND NEVER `l1_events`.** `SiteVitals`'
 * argument, at a second address and for the same two reasons: recomputing from
 * the raw event table would be slower *and would answer a different question*,
 * because the bot filter, the session stitching and the conversion definition
 * all live in the derivation. A second implementation of those is a second set
 * of numbers, which is 5146's four-marts-disagreeing failure one table along.
 * `BUILD-PLAN` §2.11.4's H bullet asks for the proof and names it: pin the
 * assertion to planted mart rows with **no raw events behind them**.
 *
 * ⛔ **IT LIVES IN `Services/Warehouse` BECAUSE NOTHING OUTSIDE IT MAY NAME A
 * DERIVED TABLE** — `WarehouseTest`'s *"nothing outside the warehouse writes to
 * a derived layer"* matches the model class and the table name as literals
 * anywhere in `app/`. So a reader for an actuation caller belongs here rather
 * than beside its caller, and this is the second reader any L2 mart has ever
 * had (5616).
 *
 * ⚠️ **A CLASS OF ITS OWN RATHER THAN A METHOD ON `SiteVitals`.** That class is
 * *"the only reader of the speed marts"* and slice K's boundary — the p75, the
 * percentile method and the bucket widths are its subject. Page counts are
 * neither speed nor a percentile, and adding them there would make the one
 * class two things at the moment slice L is about to depend on the first.
 *
 * ⚠️ **NO SAMPLE FLOOR HERE.** Whether a count is large enough to state a
 * conclusion from is the caller's question, and the two callers a floor would
 * serve want it applied to different quantities. This returns what is in the
 * mart; `App\Services\Actuation\ChangeComparison` is where a thin window
 * becomes `InsufficientData`.
 */
final readonly class PageTraffic
{
    /**
     * Pageviews and conversions for one page over a closed day range.
     *
     * ⚠️ **THE TENANT COMES FROM THE SESSION, NOT FROM AN ARGUMENT** —
     * `SiteVitals`' rule. Every query here goes through a model carrying
     * `BelongsToTenant`, so the `business_id` predicate is the global scope's
     * and row-level security stands underneath it.
     *
     * ⚠️ **BOTH SPELLINGS OF THE PATH ARE SUMMED, AND THAT IS NOT A
     * NORMALISATION.** The mart is keyed on `window.location.pathname` exactly
     * as the browser reported it, so a site that answers on both
     * `/drain-cleaning` and `/drain-cleaning/` has two rows for one page. They
     * are the same page and the visitors are not double counted, because a
     * given pageview reported one spelling. **Nothing rewrites the stored
     * value** — a reader that normalised the mart's key would disagree with
     * every other reader of the same table.
     */
    public function forPage(string $pagePath, CarbonImmutable $from, CarbonImmutable $to): PageTrafficTotals
    {
        /** @var object{pageviews: int|string|null, conversions: int|string|null} $totals */
        $totals = L2FactPageDaily::query()
            ->whereIn('page_path', self::spellings($pagePath))
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->select([
                DB::raw('coalesce(sum(pageviews), 0) AS pageviews'),
                DB::raw('coalesce(sum(conversions), 0) AS conversions'),
            ])
            ->first();

        return new PageTrafficTotals(
            (int) ($totals->pageviews ?? 0),
            (int) ($totals->conversions ?? 0),
        );
    }

    /**
     * Whether this tenant's pixel has ever reported a pageview at all.
     *
     * ⛔ **THE SEPARATOR BETWEEN "NOBODY VISITED THIS PAGE" AND "NO PIXEL IS
     * INSTALLED"**, and it is the same distinction `SiteVitals::hasEverMeasured()`
     * exists for. 4966 makes the second answer the ordinary one — ⚠️ **and its
     * stated reason, *"nothing delivers the bundle to a page yet"*, stopped
     * being true on 2026-08-18 while the answer did not (8020)**: the delivery
     * layer, the address and the install screen all exist, and **no real page
     * has ever loaded any of them**. So on every deployment that exists this is
     * still false for every tenant and the page signal is unavailable rather
     * than zero. Reporting an absent pixel as *"this change lost you all your
     * traffic"* is 229's false statement with a rollback attached.
     */
    public function hasEverMeasured(): bool
    {
        return L2FactPageDaily::query()->exists();
    }

    /**
     * @return list<string>
     */
    private static function spellings(string $pagePath): array
    {
        $trimmed = rtrim($pagePath, '/');

        if ($trimmed === '') {
            return ['/'];
        }

        return [$trimmed, $trimmed.'/'];
    }
}

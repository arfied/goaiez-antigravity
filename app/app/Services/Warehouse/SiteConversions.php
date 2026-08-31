<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\VitalSampleState;
use App\Models\L2FactDailyTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * `28` §4.3's third trigger's input: *"form-submit or booking conversion rate
 * drops >15% with ≥100 sessions."*
 *
 * ⚠️ **`l2_fact_daily_tenant`, WHICH W23 ALREADY BUILT AND NOTHING READ** —
 * 5616 records four such marts and 5617 names this one as slice L's to read
 * directly. It is site-level, which is what the mart can answer and what every
 * fix in §4.1 changes: all seven are theme- or asset-layer, so *"affected
 * pages"* is the whole site.
 *
 * ⛔ **THE FLOOR IS `SiteVitals::MINIMUM_SAMPLES` AND THAT CONSTANT IS §4.3's
 * OWN FIGURE COMING HOME.** 5605 records that §4.3 gives exactly one sample-size
 * number — *"≥100 sessions"*, on this trigger — and that `SiteVitals` borrowed
 * it for the vitals floor. Binding it here is the one use where it is not a
 * borrowing at all. ⚠️ **It is deliberately not moved**: 5723 overruled the
 * vitals floor to 50 and 5769 records why nothing changed — one constant serves
 * two floors and the second was never ruled on. This is that second floor, and
 * this slice consumes whatever the constant is rather than splitting it.
 */
final readonly class SiteConversions
{
    /**
     * The conversion rate over a closed day range, tenant-wide.
     *
     * ⚠️ **NO `Business` ARGUMENT** — `SiteVitals`' rule. The query goes through
     * a model carrying `BelongsToTenant`, so the predicate is the global scope's
     * and row-level security stands underneath it; a method taking a business is
     * a method somebody can hand another tenant's row to.
     */
    public function rate(CarbonImmutable $from, CarbonImmutable $to): ConversionReading
    {
        /** @var object{sessions: int|string|null, conversions: int|string|null} $totals */
        $totals = L2FactDailyTenant::query()
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->select([
                DB::raw('coalesce(sum(sessions), 0) AS sessions'),
                DB::raw('coalesce(sum(conversions), 0) AS conversions'),
            ])
            ->first();

        $sessions = (int) ($totals->sessions ?? 0);
        $conversions = (int) ($totals->conversions ?? 0);

        if ($sessions >= SiteVitals::MINIMUM_SAMPLES) {
            return new ConversionReading(
                VitalSampleState::Measured,
                $sessions,
                $conversions,
                intdiv($conversions * 10_000, $sessions),
            );
        }

        // ⛔ **"NOBODY HAS EVER VISITED" AND "TOO FEW VISITED THIS WEEK" ARE TWO
        // ANSWERS** (5523, and `SiteVitals::jsErrorRate()`'s own arm). The first
        // is what every tenant reads today (4966) — ⚠️ **"NOTHING DELIVERS THE
        // PIXEL BUNDLE TO A PAGE" WAS THE REASON AND STOPPED BEING TRUE ON
        // 2026-08-18; THE CONCLUSION SURVIVES ON A NARROWER ONE (8020).**
        // `PixelBundleController` serves `/p.js` and `/v/<sha>/p.js`, and
        // `/account/tracking` hands a tenant the snippet — but **no real page
        // has ever loaded it**, so every range is still empty. A delivery layer
        // existing is not a bundle being served, and a bundle being served is
        // not traffic arriving. Reporting the first answer as the second would
        // suggest waiting for data that is not coming.
        return new ConversionReading(
            $sessions === 0 && ! L2FactDailyTenant::query()->exists()
                ? VitalSampleState::NoMeasurements
                : VitalSampleState::InsufficientData,
            $sessions,
            $conversions,
        );
    }
}

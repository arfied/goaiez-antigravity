<?php

declare(strict_types=1);

namespace App\Services\Visibility;

use App\Enums\VisibilityAbsenceReason;
use App\Enums\VisibilityState;
use App\Models\GscDailySnapshot;
use App\Models\Location;
use App\Services\Gsc\SearchAnalyticsResult;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;

/**
 * The one place this application writes or reads Google Search performance
 * snapshots — and the one place a window becomes something the product may say
 * out loud.
 *
 * ⚠️ **{@see read()} IS WHERE DECISION 1084 IS ACTUALLY ENFORCED.** Everything
 * above it can express an absence; this is the method that has to choose which
 * absence, and the tempting implementation is one line —
 * `sum('impressions')`, which returns `0` for a tenant who has never connected,
 * for a tenant whose grant was revoked yesterday, and for a tenant whose
 * property genuinely got no impressions. `28` §5.3.4's rule is *"never imply the
 * number is zero"*, and those three zeroes are, in order: not our business, an
 * urgent reconnect prompt, and a real measurement. The order of the checks below
 * is the whole method.
 *
 * ⚠️ **AND `is_final` IS CHECKED, NOT IGNORED.** A window containing a day Google
 * says may still move is still `Measured` — the numbers are real — but
 * `VisibilityTotals::$final` is false, and a surface that prints a settled-looking
 * figure from it is misreporting. The distinction survives to the top of the
 * pipe because it is carried, never inferred from how recent the dates are.
 */
final class VisibilityReadings
{
    public function __construct(
        private readonly SearchConsoleProperties $properties,
        private readonly VisibilitySyncHistory $history,
    ) {}

    /**
     * Persist one Search Analytics answer, replacing whatever was there.
     *
     * ⚠️ **UPSERT, NOT INSERT, AND THAT IS THE IDEMPOTENCY.** A day arrives
     * unfinalised and has to be *replaced* when it settles, so an
     * insert-if-missing would freeze every recent day at whatever partial figure
     * we first saw. The unique index on `(location_id, date)` is the arbiter,
     * the same way `AutopilotJob::claimRun()` lets the constraint decide rather
     * than a preceding SELECT.
     *
     * @return int the number of days written
     */
    public function record(Location $location, SearchAnalyticsResult $result): int
    {
        $tenantId = Tenancy::idOrFail();

        if ($result->days === []) {
            return 0;
        }

        $rows = [];
        $now = CarbonImmutable::now();

        foreach ($result->days as $day) {
            $rows[] = [
                'business_id' => $tenantId,
                'location_id' => $location->getKey(),
                'date' => $day->date->toDateString(),
                'clicks' => $day->clicks,
                'impressions' => $day->impressions,
                'position' => $day->position,
                'is_final' => $day->final,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // `business_id` is deliberately NOT in the update list. A row's tenant is
        // fixed at insert; letting an upsert move it would be a cross-tenant
        // write dressed as a refresh — and RLS would refuse it anyway, loudly and
        // at the worst possible moment, which is a failure mode worth not having.
        GscDailySnapshot::query()->upsert(
            $rows,
            ['location_id', 'date'],
            ['clicks', 'impressions', 'position', 'is_final', 'updated_at'],
        );

        return count($rows);
    }

    /**
     * What we can honestly say about one location over one window.
     *
     * The window is inclusive at both ends and expressed in Google's Pacific-time
     * days, because that is what the stored `date` column is.
     */
    public function read(Location $location, CarbonImmutable $start, CarbonImmutable $end): VisibilityReading
    {
        Tenancy::idOrFail();

        // 1. The grant. Asked first because it is the only failure the owner can
        //    fix, and because every later check would otherwise report an
        //    absence caused by this one as though it were about the data.
        $connection = $this->properties->connectionState($location);

        if ($connection === ConnectionState::Absent) {
            return VisibilityReading::notConnected($start, $end);
        }

        if ($connection === ConnectionState::Unusable) {
            // ⚠️ Unavailable, NOT NotConnected. They connected; it stopped
            // working. Telling somebody to "connect Google Search Console" when
            // they already did reads as the product having forgotten, and they
            // will go and do it again rather than look for the revocation.
            return VisibilityReading::unavailable(
                $start,
                $end,
                VisibilityAbsenceReason::ConnectionRevoked,
            );
        }

        // 2. The binding. A tenant can be connected for weeks with no location
        //    pointed at anything — decision 1083 refuses to infer one.
        $property = $this->properties->forLocation($location);

        if ($property === null) {
            return VisibilityReading::noPropertyChosen($start, $end);
        }

        // 3. The data. `orderBy` ascending rather than `latest()`: the
        //    NULLS-ordering lint forbids a DESC on anything but `id` because
        //    Postgres sorts NULL first on DESC, and 40 of 43 tables here carry a
        //    nullable `created_at`. Ascending needs no such care and the sum does
        //    not depend on order at all — the order is for the caveat, so that
        //    "which day is unfinalised" reads in calendar order.
        $days = GscDailySnapshot::query()
            ->where('location_id', $location->getKey())
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->get();

        if ($days->isEmpty()) {
            // ⚠️ NOT `measured()` with zeroes. Google omits a day entirely when
            // there is nothing to report, so an empty window is "Google has
            // nothing to say", which for a new property is the expected state for
            // weeks (`28` §5.3.5). A measured zero is a different claim and it
            // arrives as a row of zeroes, which falls through to `measured()`
            // below exactly as it should.
            //
            // ⛔ **AND NOT `noDataYet()` EITHER, UNLESS WE ACTUALLY ASKED**
            // (9820–9839). This query is against **our own** snapshot table, so
            // an empty result is also what `gsc:sync` never having run looks
            // like, what a stopped queue looks like, and what a sync that failed
            // last night looks like — three facts about this platform, rendered
            // to a small business as *"Google Search has nothing to report for
            // this period yet."* Step 1 above already states the principle
            // — *"every later check would otherwise report an absence caused by
            // this one as though it were about the data"* — and this is the step
            // that was doing it.
            return $this->absence($location, $start, $end);
        }

        // ⛔ **A WINDOW WE HAVE ROWS FOR MAY STILL BE A WINDOW WE NEVER ASKED
        // ABOUT, AND THAT ARM IS WORSE THAN AN ABSENCE — IT IS A NUMBER.**
        // `config('gsc.lookback_days')` is 30, so one sync covers thirty days;
        // `LocalVisibility::for()`'s *earlier* window is days 30–57, which for a
        // recently connected location overlaps the sync by two days. Those two
        // days used to arrive here as `Measured` and get compared against a full
        // twenty-eight, so the screen said *"Fewer people found you in Google
        // Search this month"* about a decline that never happened —
        // `VisibilityMovement`'s own documented trap, arriving through the one
        // door it does not cover.
        if ($this->history->windowPredatesFirstRead((int) $location->getKey(), $start)) {
            return VisibilityReading::notReadYet(
                $start,
                $end,
                VisibilityAbsenceReason::PeriodBeforeFirstRead,
            );
        }

        return VisibilityReading::measured($start, $end, VisibilityTotals::sum(
            array_values($days->map(static fn (GscDailySnapshot $day): array => [
                'clicks' => $day->clicks,
                'impressions' => $day->impressions,
                'position' => $day->position,
                'final' => $day->is_final,
            ])->all()),
        ));
    }

    /**
     * Whose doing is an empty window.
     *
     * ⛔ **THE ORDER IS THE METHOD, EXACTLY AS IT IS IN {@see self::read()}.**
     * Coverage is asked **before** the sync's own account of itself, because a
     * window from before our first read is unanswerable however well last
     * night's sync went — a healthy `Succeeded` run says nothing about days it
     * never requested.
     *
     * ⚠️ **AND `noDataYet()` IS THE LAST ARM RATHER THAN THE FIRST.** It is the
     * only one of the four that is a claim about Google, so it is the only one
     * that has to be earned; the three above it are earned by a row in
     * `automation_runs` that says what we did.
     */
    private function absence(
        Location $location,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): VisibilityReading {
        $locationId = (int) $location->getKey();

        if ($this->history->windowPredatesFirstRead($locationId, $start)) {
            return VisibilityReading::notReadYet(
                $start,
                $end,
                VisibilityAbsenceReason::PeriodBeforeFirstRead,
            );
        }

        $reason = $this->history->searchAbsence($locationId);

        if ($reason === null) {
            return VisibilityReading::noDataYet($start, $end);
        }

        return $reason->state() === VisibilityState::NotReadYet
            ? VisibilityReading::notReadYet($start, $end, $reason)
            : VisibilityReading::unavailable($start, $end, $reason);
    }
}

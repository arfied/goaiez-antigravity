<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\VisibilityState;
use App\Models\GscSiteProperty;
use App\Models\Location;
use App\Services\Visibility\SearchConsoleProperties;
use App\Services\Visibility\VisibilityMovement;
use App\Services\Visibility\VisibilityReading;
use App\Services\Visibility\VisibilityReadings;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * What this application can honestly say about one location's Google Search
 * visibility, right now.
 *
 * ⚠️ **THIS EXISTS BECAUSE PHASE 1 SHIPS NO SCREEN, AND A READER WITH NO CALLER
 * IS THE DEFECT THIS ROW WAS SEQUENCED TO AVOID.** Decision 1080 requires the
 * slice to be complete vertically — connection, client, store, reader — precisely
 * because *"this codebase's most-repeated defect is the other shape: a table, an
 * enum case or a service with no writer, no caller or no reader"*, decision 272's
 * family at thirteen recorded instances plus 620's inversion. An Ops command is
 * the same answer `ZernioGbpClient`'s docblock reaches for when it says the only
 * permitted callers are tests and an Ops-side console command, and it is a tool
 * support genuinely needs on the phone: *"why does this customer see nothing?"*
 * has five different answers and this prints which one.
 *
 * It is **read-only and files nothing**. It opens no impersonation session and
 * writes no audit row, because it reads no customer data — a location's own
 * search performance is the tenant's business metric, not a person's. Compare
 * `AccountDirectory`, which does file `business.viewed_by_staff` (decision 804),
 * and correctly: it reads the account.
 *
 * ⚠️ **AND IT DELIBERATELY PRINTS NO POSITION ON THE HEADLINE LINE.** Decision
 * 1085: `28` §5.3.3's locked language rule is *"state movement and direction,
 * never a position"*, and GSC's `position` is an impression-weighted average
 * rather than a rank, so printing it as one would be both a policy breach and a
 * category error. §5.3.3 does put query positions on the **Advanced** surface, so
 * it is printed below, under a heading that says what it is.
 */
#[Signature('gsc:status {business : The business id} {--location= : One location, rather than all of them} {--days=28 : Window length in days}')]
#[Description('Print what can honestly be said about a business\'s Google Search visibility')]
final class ShowSearchConsoleStatus extends Command
{
    public function handle(): int
    {
        $businessId = (int) $this->argument('business');
        $days = max(1, (int) $this->option('days'));
        $only = $this->option('location');

        // ⚠️ THE BUSINESS ID IS AN ARGUMENT AND CANNOT BE DERIVED FROM THE
        // LOCATION, which is the shape an operator would expect. `locations` is
        // RLS `ENABLE`+`FORCE`d on `app.business_id`, so with no tenant
        // established every row is hidden and `Location::find()` returns null —
        // and `withoutGlobalScopes()` does not help, because the policy is in the
        // database beneath the application scope. Decision 569's wall, and this
        // is its fifth instance; the by-reference pattern (`28` §9.3) is the
        // answer here as it was there.
        /** @var int $exit */
        $exit = Tenancy::actingAs($businessId, function () use ($days, $only): int {
            $locations = Location::query()
                ->when(is_string($only) && $only !== '', fn ($query) => $query->whereKey((int) $only))
                ->orderBy('id')
                ->get();

            if ($locations->isEmpty()) {
                $this->error('No locations readable for that business.');

                return self::FAILURE;
            }

            foreach ($locations as $location) {
                $this->report($location, $days);
            }

            return self::SUCCESS;
        });

        // ⚠️ NO `Tenancy::forgetAll()` HERE, AND THE ABSENCE IS DELIBERATE.
        // `gsc:sync` and the three sweeps it copies all end with one, so the
        // symmetric-looking thing to do is add a fourth. Those commands need it
        // because they call `Tenancy::setUser()` in a loop and nothing unwinds
        // it. This one establishes a tenant only through `actingAs()`, which
        // restores in a `finally` — so the call would be a line that cannot
        // change the outcome, and its test would pass with the line deleted.
        // Decision 411's shape, found by mutation on this slice's own sibling
        // test rather than by review.
        return $exit;
    }

    private function report(Location $location, int $days): void
    {
        $readings = app(VisibilityReadings::class);

        [$start, $end] = self::window($days);
        [$priorStart, $priorEnd] = self::window($days, $days);

        $current = $readings->read($location, $start, $end);
        $earlier = $readings->read($location, $priorStart, $priorEnd);

        $property = app(SearchConsoleProperties::class)->forLocation($location);

        $this->newLine();
        $this->line('Location  #'.$location->getKey().'  '.$location->name);
        $this->line('Property  '.($property instanceof GscSiteProperty ? (string) $property->site_url : '— none chosen —'));
        $this->line('Window    '.$start->toDateString().' to '.$end->toDateString().' (Google\'s Pacific-time days)');
        $this->newLine();

        $this->line($this->headline($current, $earlier));

        if (! $current->isMeasured()) {
            return;
        }

        $totals = $current->totals();

        if (! $totals->final) {
            // ⚠️ Printed rather than hidden, and it is the whole reason
            // `is_final` is a column. A window containing days Google is still
            // counting is a number that will change, and a support agent quoting
            // it as settled is the misreport decision 1084 forbids one level up.
            $this->warn('  ⚠ Some days in this window are not final — these numbers will still change.');
        }

        $this->newLine();
        $this->line('Advanced (`28` §5.3.3 — not the owner\'s normal surface)');
        $this->line('  clicks              '.$totals->clicks);
        $this->line('  impressions         '.$totals->impressions);
        $this->line('  click-through rate  '.number_format($totals->clickThroughRate() * 100, 2).'%');
        $this->line('  average position    '.number_format($totals->averagePosition, 1).'  (impression-weighted average, NOT a rank)');
        $this->line('  days with data      '.$totals->days);
    }

    /**
     * The one sentence this command is willing to say without qualification.
     */
    private function headline(VisibilityReading $current, VisibilityReading $earlier): string
    {
        return match ($current->state) {
            VisibilityState::NotConnected => 'Search Console is not connected for this business.',
            VisibilityState::NoPropertyChosen => 'Connected, but no site property has been chosen for this location.',
            VisibilityState::NoDataYet => 'Connected and mapped — Google has no data for this window yet.',
            // ⚠️ THE OPERATOR GETS THE CODE, NOT THE OWNER'S SENTENCE
            // (9820–9839). `VisibilityAbsenceReason::sentence()` is written for
            // somebody who owns the business; what a support agent needs here is
            // the short code they can grep `automation_runs` for. The `?? 'unknown'`
            // stays and is now genuinely unreachable — both states below carry a
            // reason by construction — which is why it is not asserted anywhere.
            VisibilityState::NotReadYet => 'Not read yet: '.($current->reason->value ?? 'unknown').'.',
            VisibilityState::Unavailable => 'Search Console cannot be read right now: '.($current->reason->value ?? 'unknown').'.',
            VisibilityState::Measured => $this->movementSentence($current, $earlier),
        };
    }

    /**
     * `28` §5.3.3's own shape — movement and direction, never a position.
     *
     * ⚠️ The `Indeterminate` branch is the load-bearing one. Its alternative is
     * treating an unreadable earlier window as zero, which prints a rise from
     * nothing — a success story nobody measured. {@see VisibilityMovement} refuses
     * to produce a direction unless both windows are measured, and this is what
     * that refusal looks like to a reader.
     */
    private function movementSentence(VisibilityReading $current, VisibilityReading $earlier): string
    {
        $now = $current->totals()->impressions;

        return match (VisibilityMovement::between($current, $earlier)) {
            VisibilityMovement::Up => "More people found this business in Google Search — {$now} up from {$earlier->totals()->impressions}.",
            VisibilityMovement::Down => "Fewer people found this business in Google Search — {$now} down from {$earlier->totals()->impressions}.",
            VisibilityMovement::Unchanged => "As many people found this business in Google Search as last period — {$now}.",
            VisibilityMovement::Indeterminate => "{$now} people found this business in Google Search. No comparable earlier period, so there is no movement to report.",
        };
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private static function window(int $days, int $offset = 0): array
    {
        // Google's day boundary, not ours — the stored `date` column is a
        // Pacific-time day and building a window in UTC is off by one for part of
        // every day.
        $end = CarbonImmutable::now('America/Los_Angeles')->startOfDay()->subDays($offset + 1);

        return [$end->subDays($days - 1), $end];
    }
}

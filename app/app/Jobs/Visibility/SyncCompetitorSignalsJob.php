<?php

declare(strict_types=1);

namespace App\Jobs\Visibility;

use App\Enums\AutopilotActionType;
use App\Enums\CompetitorAbsenceReason;
use App\Exceptions\PlacesBudgetExhausted;
use App\Exceptions\PlacesRequestFailed;
use App\Jobs\AutopilotJob;
use App\Models\Location;
use App\Services\Visibility\CompetitorSignals;
use App\Services\Visibility\CompetitorSiteNotes;

/**
 * Refresh one location's Places competitor set and snapshots (`28` §5.5).
 *
 * Silent in the feed — daily plumbing, like {@see SyncSearchConsoleJob}.
 * Idempotency is the upsert on `(location_id, place_id)` plus a new snapshot
 * row; no job-level key (1089 / SyncSearchConsoleJob's reasoning).
 */
final class SyncCompetitorSignalsJob extends AutopilotJob
{
    private string $handoffReason = 'unknown';

    public function automationKey(): string
    {
        return 'visibility.competitor_signals';
    }

    protected function activityAction(): ?AutopilotActionType
    {
        return null;
    }

    protected function canExecute(): bool
    {
        $location = $this->location();

        if (! $location instanceof Location) {
            $this->handoffReason = 'location_missing';

            return false;
        }

        if ($location->google_place_id === null || trim($location->google_place_id) === '') {
            $this->handoffReason = 'no_place_id';

            return false;
        }

        return true;
    }

    protected function execute(): array
    {
        $location = $this->location();

        if (! $location instanceof Location) {
            return ['outcome' => 'unavailable', 'reason' => 'location_missing'];
        }

        // ⚠️ "Could not ask Google" and "asked, nobody is nearby" must not close
        // the same way. Both used to arrive here as a `0` the service had
        // swallowed, so a run recorded `refreshed, competitors: 0` and closed
        // Succeeded — and an integration that had been failing for a month read
        // as a quiet neighbourhood. `SyncSearchConsoleJob` draws the same line
        // with the same vocabulary.
        // ⛔ **AND `CompetitorSignals::compare()` NOW READS THESE ROWS BACK**
        // (9820–9839), so a reason recorded here is what an owner is told when
        // there is no comparison. That is why they are `CompetitorAbsenceReason`
        // values rather than free strings: the code written here has to be one
        // the reading layer can resolve, and `->value` is what makes a rename
        // impossible to do on only one side.
        try {
            $kept = app(CompetitorSignals::class)->refresh($location);
            $noted = app(CompetitorSiteNotes::class)->refreshForLocation($location);
        } catch (PlacesBudgetExhausted) {
            return [
                'outcome' => 'unavailable',
                'reason' => CompetitorAbsenceReason::LookupBudgetSpent->value,
            ];
        } catch (PlacesRequestFailed $e) {
            return [
                'outcome' => 'unavailable',
                'reason' => self::lookupFailure($e)->value,
            ];
        }

        return ['outcome' => 'refreshed', 'competitors' => $kept, 'site_notes' => $noted];
    }

    protected function handoff(): array
    {
        return ['reason' => $this->handoffReason];
    }

    /**
     * Whose doing a failed Places call was.
     *
     * ⛔ **`'places_request_failed'` WAS ONE CODE OVER TWO PARTIES**, and once
     * the reading layer started turning these into sentences that stopped being
     * survivable: `unauthorized`, `unconfigured`, `bad_request`, `quota` and any
     * unclassified 4xx are all facts about **our own** Google Cloud project and
     * our own key, while `unreachable` and `server_error` are Google's. Telling
     * an owner *"Google could not answer"* about our own unset API key is
     * 9740–9759's defect on a second surface, and telling them *"that is on us"*
     * about a Google outage is the same error pointed the other way.
     *
     * ⚠️ **THE DEFAULT IS OURS**, deliberately. `PlacesRequestFailed::from()`
     * already routes every 5xx to `server_error`, so an unrecognised code is a
     * 4xx or a shape this platform produced — and where the attribution is
     * genuinely unknown, claiming it ourselves is the direction that cannot
     * become a false accusation.
     */
    private static function lookupFailure(PlacesRequestFailed $failure): CompetitorAbsenceReason
    {
        if ($failure->reason === 'unreachable' || $failure->reason === 'server_error') {
            return CompetitorAbsenceReason::LookupUnanswered;
        }

        return CompetitorAbsenceReason::LookupRefused;
    }
}

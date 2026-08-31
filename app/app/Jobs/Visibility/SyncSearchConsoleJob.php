<?php

declare(strict_types=1);

namespace App\Jobs\Visibility;

use App\Contracts\SearchConsoleClient;
use App\Enums\AutopilotActionType;
use App\Enums\OauthProvider;
use App\Exceptions\ProviderNotConnected;
use App\Exceptions\SearchConsoleRequestFailed;
use App\Jobs\AutopilotJob;
use App\Models\Business;
use App\Models\Location;
use App\Services\Oauth\TokenService;
use App\Services\Visibility\ConnectionState;
use App\Services\Visibility\SearchConsoleProperties;
use App\Services\Visibility\VisibilityReading;
use App\Services\Visibility\VisibilityReadings;
use Carbon\CarbonImmutable;

/**
 * Pull one location's Google Search performance into `gsc_daily_snapshots`.
 *
 * ## Idempotency lives in the upsert, and {@see idempotencyKey()} is null on
 * purpose
 *
 * The base class calls null *"correct for genuinely repeating work — a nightly
 * sweep is meant to run every night"*, and this is one. It is also the only
 * choice that leaves `$tries` meaning anything: `AutopilotJob::claimRun()` lets a
 * unique violation refuse a duplicate, so **a job with an idempotency key cannot
 * be retried by the queue at all** — the retry hits the claim its own failed
 * attempt already made and returns without doing the work, which is decision
 * 356's finding wearing a different hat. With the key absent, a Google 5xx at
 * 3am is recovered sixty seconds later instead of tomorrow.
 *
 * Running twice is therefore harmless rather than prevented:
 * {@see VisibilityReadings::record()} upserts on `(location_id, date)`, so the
 * second run replaces the same rows with the same numbers. The cost of a
 * duplicate is one Search Analytics call against a 30,000,000 QPD project quota.
 *
 * ## Why the window overlaps, every day, on purpose
 *
 * `config('gsc.lookback_days')` is 30 rather than 1. Google's most recent days
 * are still being counted, so a day first read while incomplete has to be read
 * again once it settles — an incremental "fetch yesterday" sync would freeze
 * every day at its partial figure and the numbers would never be right. Re-reading
 * a settled day costs one row in an upsert.
 *
 * ## execute() and handoff()
 *
 * `29` §2 rule 44. `execute()` is the GSC read. `handoff()` is what an honest
 * system does when there is no GSC to read: there is **no second source for
 * Google's own search data** — the pixel does not exist and GBP Performance is
 * unapproved (decision 1081) — so the handoff cannot produce the number by
 * another route, and pretending otherwise would be worse than saying nothing. It
 * records the classified reason on the run row, which is what the reading layer
 * and Ops read, and it deliberately files **nothing to the activity feed**: this
 * runs daily, and "still not connected" repeated every morning is exactly the
 * automation the base class means when it says one whose only honest title is
 * *"checked something and found nothing"* makes the feed worse. The owner-facing
 * surface for this state is {@see VisibilityReading},
 * which says it once, in place, where somebody is looking.
 */
final class SyncSearchConsoleJob extends AutopilotJob
{
    /**
     * Why the full path was not taken, set by {@see canExecute()} so
     * {@see handoff()} can say which of four things happened rather than
     * re-deriving it and possibly disagreeing.
     */
    private string $handoffReason = 'unknown';

    public function automationKey(): string
    {
        return 'visibility.search_console_sync';
    }

    /**
     * Silent in the feed, exhaustive in `automation_runs`.
     *
     * The base class's own rule: *"every action is visible" and "the feed is
     * worth reading" are in tension*. A daily numbers refresh is plumbing, and
     * `28` §5.3.3's owner-facing sentence is a monthly movement, not a sync
     * notification.
     */
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

        $properties = app(SearchConsoleProperties::class);

        $connection = $properties->connectionState($location);

        if ($connection === ConnectionState::Absent) {
            $this->handoffReason = 'not_connected';

            return false;
        }

        if ($connection === ConnectionState::Unusable) {
            // The owner already has a Reconnect notification from
            // TokenService::markUnusable(), which has its own once-only guard.
            // Nothing more to say here.
            $this->handoffReason = 'connection_revoked';

            return false;
        }

        if ($properties->forLocation($location) === null) {
            $this->handoffReason = 'no_property_chosen';

            return false;
        }

        return true;
    }

    protected function execute(): array
    {
        $location = $this->location();

        if (! $location instanceof Location) {
            // canExecute() already refuses this, so reaching it means the row
            // vanished between the two — a real race on a cascade delete.
            return ['outcome' => 'unavailable', 'reason' => 'location_missing'];
        }

        $properties = app(SearchConsoleProperties::class);
        $property = $properties->forLocation($location);

        if ($property === null) {
            return ['outcome' => 'unavailable', 'reason' => 'no_property_chosen'];
        }

        // The tenant this job was dispatched for, not the location's relation.
        // `AutopilotJob::handle()` has already established it, so the two cannot
        // disagree — and reading it from the job's own field means the vendor
        // call names the tenant the run row names.
        $business = Business::findOrFail($this->businessId);

        [$start, $end] = self::window();

        try {
            $result = app(SearchConsoleClient::class)->dailyMetrics(
                $business,
                (string) $property->site_url,
                $start,
                $end,
            );
        } catch (ProviderNotConnected) {
            // The vault refused between canExecute() and here — a token that
            // expired and could not be refreshed. Not a failure of this job and
            // not retryable: the owner has already been asked to reconnect.
            return ['outcome' => 'unavailable', 'reason' => 'connection_revoked'];
        } catch (SearchConsoleRequestFailed $e) {
            return $this->classify($e, $business);
        }

        $written = app(VisibilityReadings::class)->record($location, $result);

        return [
            'outcome' => 'measured',
            'days_written' => $written,
            'days_dropped' => $result->dropped,
            'window_start' => $start->toDateString(),
            'window_end' => $end->toDateString(),
            // ⚠️ Recorded rather than inferred. Whether the window still contains
            // days Google may change is the difference between a settled number
            // and a moving one, and a reader guessing it from how recent the
            // dates are hardcodes a lag Google documents as a range.
            'first_incomplete_date' => $result->firstIncompleteDate?->toDateString(),
        ];
    }

    protected function handoff(): array
    {
        return [
            'outcome' => 'handoff',
            'reason' => $this->handoffReason,
            // Outcome language, and the only thing here a person can act on.
            // Three of the four reasons are the owner's to fix and the fourth
            // (`location_missing`) is ours.
            'owner_action' => match ($this->handoffReason) {
                'not_connected' => 'connect_search_console',
                'connection_revoked' => 'reconnect_search_console',
                'no_property_chosen' => 'choose_search_console_property',
                default => 'none',
            },
        ];
    }

    /**
     * Turn a classified vendor failure into either a retry or a recorded state.
     *
     * ⚠️ **THE SPLIT IS DECISION 364'S LESSON AND DECISION 532'S TOGETHER.** A
     * retryable failure is rethrown so the queue's backoff ladder handles it. A
     * non-retryable one — a permission this account does not hold, a property
     * that is gone — is **recorded and returned, never thrown**, because
     * throwing burns three attempts against a condition that clears only when a
     * human does something, and 364 records that costing every review submitted
     * during the window. And the two 403s are not the same: `quotaExceeded` is
     * ours and retryable, `insufficientPermissions` is the tenant's Search
     * Console configuration and is not.
     *
     * @return array<string, mixed>
     *
     * @throws SearchConsoleRequestFailed when it is worth trying again
     */
    private function classify(SearchConsoleRequestFailed $e, Business $business): array
    {
        if ($e->revoked) {
            // ⚠️ A 401 here means the grant died since the last successful call,
            // and nothing else in this application would notice: the refresh
            // sweep only sees tokens that expire, and a *revoked* grant with an
            // unexpired access token refreshes and reads fine right up until it
            // does not. Flipping the connection is what raises the owner's
            // Reconnect prompt, and markUnusable() carries its own once-only
            // guard so a daily sync cannot turn it into a daily notification.
            $connection = app(TokenService::class)->connection($business, OauthProvider::Gsc);

            if ($connection !== null) {
                app(TokenService::class)->markUnusable($connection, 'gsc_'.$e->reason);
            }

            return ['outcome' => 'unavailable', 'reason' => $e->readingReason()];
        }

        if ($e->retryable) {
            throw $e;
        }

        return ['outcome' => 'unavailable', 'reason' => $e->readingReason()];
    }

    /**
     * The window this sync asks Google for, in Google's own Pacific-time days.
     *
     * ⚠️ **`America/Los_Angeles`, NOT UTC AND NOT THE APP TIMEZONE.** The
     * reference states the range is *"YYYY-MM-DD format in PT time"*. Building
     * the window from `now()` in UTC asks for a day that has not started in PT
     * for part of every day, and the error is one day — invisible in a total and
     * exactly wrong on a boundary.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function window(): array
    {
        $today = CarbonImmutable::now('America/Los_Angeles')->startOfDay();

        $end = $today->subDays(max(0, (int) config('gsc.skip_recent_days', 2)));
        $start = $end->subDays(max(1, (int) config('gsc.lookback_days', 30)) - 1);

        return [$start, $end];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Visibility;

use App\Enums\AutomationRunStatus;
use App\Enums\CompetitorAbsenceReason;
use App\Enums\ReviewSyncAbsenceReason;
use App\Enums\VisibilityAbsenceReason;
use App\Enums\VisibilityState;
use App\Models\AutomationRun;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;

/**
 * What this platform has actually asked Google about one location, read off the
 * record that already holds it.
 *
 * ⛔ **THE POINT IS THAT AN EMPTY COLLECTION IS NOT EVIDENCE THAT A QUESTION WAS
 * ASKED** (9820–9839). `CompetitorSignals::compare()` and
 * `VisibilityReadings::read()` both answered *"there is nothing there"* off an
 * empty query against **our own tables**, and an empty local table is what a
 * spent cost cap, an unrun sweep, a stopped queue and a genuine absence all look
 * like. This class is the second half of both questions.
 *
 * ## ⚠️ Why `automation_runs` and not a new column
 *
 * Because the fact is already recorded there, per run, per location, under the
 * tenant it happened to, with the distinction already drawn: both sync jobs
 * return `['outcome' => 'unavailable', 'reason' => …]` versus a success, and
 * `SyncCompetitorSignalsJob`'s own comment has said since it shipped that
 * *"'Could not ask Google' and 'asked, nobody is nearby' must not close the same
 * way"*. **The writer already knew; nothing read it.** A `last_synced_at` column
 * would be a second source of truth for a fact this table holds, and a
 * tenant-owned column cannot be backfilled by a migration anyway (`CLAUDE.md`
 * §Convention tests), so it would start life null and indistinguishable from
 * never-synced for every existing row.
 *
 * ⛔ **"`automation_runs` HAS NO PRUNER" WAS TRUE AND IS NOT — CORRECTED
 * 2026-08-26 (10184, 10185, 10260–10269).** `automation:prune-runs` now
 * sweeps it nightly, once an operator states a period in
 * `automation.retention_days` — unset on every deployment today, so *"no
 * row"* still means *"never ran"* in practice. ⚠️ **`lastRun()` did not need
 * to change, which is the reading worth carrying**: this method already asks
 * for exactly the row `App\Services\Automation\AutomationRunRetention::survivorIds()`
 * now keeps for ever, regardless of any period — *a counter may be pruned, a
 * state may not*, 9934's shape. **What moved is the guarantee that row
 * survives, not this query.** `tests/Feature/Architecture/AutomationRunSurvivorTest.php`
 * is the proof; `NeverRead`/`NeverChecked` are correct after a real prune runs,
 * not merely before one is written.
 *
 * ## ⚠️ What it deliberately does not answer
 *
 * **Whether coverage has a hole in the middle.** {@see self::firstReadStart()}
 * is the earliest window any successful read asked for, so a location synced for
 * a year, silent for two months and synced again reports coverage across the
 * silence. `config('gsc.lookback_days')` is 30 and every sync re-asks the whole
 * thirty days, so a gap shorter than that heals itself; a longer one does not
 * and this cannot see it. Named rather than glossed.
 *
 * ## ⚠️ Its third subject is not visibility, and the namespace is the compromise
 *
 * **Google reviews joined it 2026-08-26** (10120–10139). `reviews.google_sync`
 * records the identical `outcome`/`reason` shape on the identical table under
 * the identical tenant, and `SyncGoogleReviewsJob` had been writing it, per run,
 * per location, since the day it shipped, with nothing reading it — 9821 in a
 * second place. ⛔ **The alternative was a `Services\Reviews\ReviewSyncHistory`
 * holding its own copy of {@see self::lastRun()}**, and a guard holding a
 * re-typed twin of the query it reads is `CLAUDE.md`'s own named failure: the
 * `NULLS LAST` ordering, the `id` tiebreak and the `Running` exclusion are three
 * separate correctnesses, and a second copy of them is three chances to
 * disagree about which run is *the latest*. ⚠️ **So the namespace is now wider
 * than the class's subject and the class name is not**; if a fourth automation
 * joins, moving this to `App\Services\Automation` is the edit, and it is a
 * `use` change and nothing else.
 *
 * **The recent end of a window.** Coverage is asked about the *start* only, and
 * that is a decision rather than an omission: `LocalVisibility` builds its
 * window from `now()` in the app timezone while `SyncSearchConsoleJob::window()`
 * builds its own in `America/Los_Angeles`, so on a perfectly healthy install the
 * last recorded `window_end` is routinely a day behind the window being read.
 * A strict end check would fire on every deployment every day. **`is_final`
 * already carries the recent end** — `VisibilityTotals::$final` is what tells a
 * surface those numbers are still moving — and nothing carried the old end.
 */
final class VisibilitySyncHistory
{
    public const string SEARCH_CONSOLE_AUTOMATION = 'visibility.search_console_sync';

    public const string COMPETITOR_AUTOMATION = 'visibility.competitor_signals';

    /**
     * ⚠️ **THE STRING IS `SyncGoogleReviewsJob::automationKey()`'s AND IS PINNED
     * BY A TEST RATHER THAN BY THIS COMMENT.** A typo here is invisible: the
     * query matches nothing, `lastRun()` answers null, and every surface reads
     * {@see ReviewSyncAbsenceReason::NeverRead} for ever — a lint that matches
     * nothing passing vacuously, wearing an owner-facing sentence.
     */
    public const string REVIEW_SYNC_AUTOMATION = 'reviews.google_sync';

    /**
     * The most recent terminal run of one automation for one location.
     *
     * ⚠️ **`orderByDesc('started_at')` WITH `id` UNDERNEATH.** Two runs of a
     * daily sweep can share a `started_at` to the second on a re-dispatch, and
     * *"the latest"* has to be total or this method answers differently on two
     * calls in one request.
     */
    public function lastRun(int $locationId, string $automationKey): ?AutomationRun
    {
        Tenancy::idOrFail();

        return AutomationRun::query()
            ->where('automation_key', $automationKey)
            ->where('location_id', $locationId)
            ->where('status', '!=', AutomationRunStatus::Running)
            // ⚠️ `NULLS LAST` SPELLED OUT, ON `ConventionsTest`'s RULE AND FOR
            // ITS REASON: `automation_runs.started_at` is nullable, Postgres
            // sorts NULL first on a DESC, and an undated row would then be
            // "the latest run" for ever. The `id` tiebreak is what makes
            // "latest" total — two dispatches of a daily sweep can share a
            // second, and this method must not answer differently on two calls
            // in one request.
            ->orderByRaw('started_at DESC NULLS LAST')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Why Search performance is absent, or null when the absence is Google's own
     * answer and may be reported as such.
     *
     * ⛔ **NULL IS THE ONLY ARM THAT PERMITS
     * {@see VisibilityState::NoDataYet}**, and it is reached only from
     * a run that recorded a measured read. Every other shape — no row, a failure,
     * a skip, a handoff, a recorded vendor reason — is an absence with a cause,
     * and naming the cause is the whole slice.
     */
    public function searchAbsence(int $locationId): ?VisibilityAbsenceReason
    {
        $run = $this->lastRun($locationId, self::SEARCH_CONSOLE_AUTOMATION);

        if ($run === null) {
            return VisibilityAbsenceReason::NeverRead;
        }

        return match ($run->status) {
            // ⚠️ **`Abandoned` JOINS `Failed` RATHER THAN FALLING TO `default`,
            // AND THE `default` IS WHY THIS ARM HAD TO BE EDITED** (9964). That
            // arm reads `output.outcome`, and an abandoned run has none — so it
            // returns `null`, which is the one answer that permits
            // `VisibilityState::NoDataYet`. A worker killed mid-read would have
            // been reported as *Google has no data for you*, which is exactly
            // the false absence this class exists to close.
            AutomationRunStatus::Failed,
            AutomationRunStatus::Abandoned => VisibilityAbsenceReason::LastReadFailed,
            AutomationRunStatus::Skipped => VisibilityAbsenceReason::ReadingStopped,
            // ⚠️ A handoff is the no-API path, and its reason is one of
            // `canExecute()`'s four. Three of them — not connected, revoked, no
            // property — are answered by `read()`'s own earlier steps and never
            // reach here; the fourth is `location_missing`, which cannot be true
            // of a location we are holding. So this arm exists for a handoff
            // reason nobody has written yet, and it fails to "we have not read
            // it", never to "Google had nothing".
            AutomationRunStatus::HandedOff => VisibilityAbsenceReason::NeverRead,
            default => $this->recordedSearchReason($run),
        };
    }

    /**
     * The earliest day any successful read of this location asked Google for, or
     * null when nothing has been recorded.
     *
     * Taken from `output.window_start`, which `SyncSearchConsoleJob` has
     * recorded since the day it shipped — *"recorded rather than inferred"*, its
     * own words about the sibling field.
     */
    public function firstReadStart(int $locationId): ?CarbonImmutable
    {
        Tenancy::idOrFail();

        // ⚠️ ORDERED ASCENDING AND TAKEN FIRST RATHER THAN AGGREGATED. `min()`
        // over a `->>` path needs raw SQL that `Builder::value()` then discards
        // by re-selecting its own column, and the ordering is exact anyway:
        // these are `YYYY-MM-DD` strings, where lexicographic and chronological
        // are the same order. Ascending needs no `NULLS LAST` care.
        $output = AutomationRun::query()
            ->where('automation_key', self::SEARCH_CONSOLE_AUTOMATION)
            ->where('location_id', $locationId)
            ->where('status', AutomationRunStatus::Succeeded)
            ->whereRaw("output->>'window_start' is not null")
            ->orderByRaw("output->>'window_start' asc")
            ->value('output');

        $earliest = is_array($output) ? ($output['window_start'] ?? null) : null;

        if (! is_string($earliest) || $earliest === '') {
            return null;
        }

        return CarbonImmutable::parse($earliest)->startOfDay();
    }

    /**
     * Whether a window is known to begin before this platform's first read.
     *
     * ⚠️ **FAILS OPEN, AND THAT IS THE DIRECTION THAT MATTERS HERE.** A null
     * `firstReadStart()` means no successful run row carries a window, which on
     * this tree can only be a row written by something that is not
     * `SyncSearchConsoleJob` — so treating it as "not covered" would print *"this
     * period is from before we started reading"* over rows sitting right there in
     * `gsc_daily_snapshots`. The never-read case is caught by
     * {@see self::searchAbsence()} instead, which keys on the run existing at all.
     */
    public function windowPredatesFirstRead(int $locationId, CarbonImmutable $start): bool
    {
        $first = $this->firstReadStart($locationId);

        return $first !== null && $first->greaterThan($start->startOfDay());
    }

    /**
     * Why a nearby comparison is absent, or null when Google was asked and the
     * neighbourhood is genuinely empty.
     */
    public function competitorAbsence(int $locationId): ?CompetitorAbsenceReason
    {
        $run = $this->lastRun($locationId, self::COMPETITOR_AUTOMATION);

        if ($run === null) {
            return CompetitorAbsenceReason::NeverChecked;
        }

        return match ($run->status) {
            // ⚠️ **`Abandoned` JOINS `Failed`** — `searchAbsence()`'s reasoning
            // one method over, and for the same `default` arm.
            AutomationRunStatus::Failed,
            AutomationRunStatus::Abandoned => CompetitorAbsenceReason::LastCheckFailed,
            AutomationRunStatus::Skipped => CompetitorAbsenceReason::CheckingStopped,
            // `canExecute()`'s two handoff reasons are `location_missing` and
            // `no_place_id`; the second is answered by `compare()`'s first step
            // and the first cannot be true here. Never "nobody is nearby".
            AutomationRunStatus::HandedOff => CompetitorAbsenceReason::NeverChecked,
            default => $this->recordedCompetitorReason($run),
        };
    }

    /**
     * Why the most recent look at one location's **Google reviews** read none,
     * or null when the last run recorded a read.
     *
     * ⛔ **NULL MEANS *THE LAST RUN RECORDED A READ*, AND NOTHING WIDER.** It is
     * not *"reviews are arriving"*, not *"Google answered with data"*, and not
     * *"this listing is verified"* — a `synced` outcome is written after the
     * upsert whether the page held forty reviews or none. Every caller renders
     * **nothing at all** on a null for exactly that reason (9921): the absence
     * of a sentence means only *nothing here is stopping it*.
     *
     * ⚠️ **IT KNOWS NOTHING ABOUT THE CONNECTION AND DELIBERATELY ASKS
     * NOTHING.** {@see ReviewSyncAbsenceReason::NotConnectedAtLastLook} is a
     * fact about the run row, in the past tense, and the two surfaces that
     * render it are both standing inside a *currently usable* connection —
     * `usableLocationIds()` on Home and the *Connected* branch on the Google
     * reviews screen. **That is the callers' precondition rather than this
     * method's**, and it is stated here so the next caller knows it is taking
     * one on.
     */
    public function reviewAbsence(int $locationId): ?ReviewSyncAbsenceReason
    {
        $run = $this->lastRun($locationId, self::REVIEW_SYNC_AUTOMATION);

        if ($run === null) {
            return ReviewSyncAbsenceReason::NeverRead;
        }

        return match ($run->status) {
            // ⚠️ **`Abandoned` JOINS `Failed`** — `searchAbsence()`'s reasoning
            // two methods up, and for the same `default` arm. A worker killed
            // mid-read leaves no `output` at all, so falling to the recorded
            // reader would answer null and report *we read your reviews*.
            AutomationRunStatus::Failed,
            AutomationRunStatus::Abandoned => ReviewSyncAbsenceReason::LastReadFailed,
            AutomationRunStatus::Skipped => ReviewSyncAbsenceReason::ReadingStopped,
            // ⛔ **A HANDOFF MAY NEVER FALL TO NULL, WHICH IS WHY THIS ARM IS
            // NOT FOLDED INTO THE `default`.** `handoff()` writes
            // `outcome => 'handoff'` with one of three reasons, and a row whose
            // `output` is malformed — an older deploy, a truncated write —
            // would otherwise say *we read your reviews* about a run that never
            // reached the vendor at all.
            AutomationRunStatus::HandedOff => $this->recordedReviewReason($run)
                ?? ReviewSyncAbsenceReason::LastReadFailed,
            default => $this->recordedReviewReason($run),
        };
    }

    /**
     * The one answer that is true of a whole account's Google reading, or null
     * when there is nothing true to say about all of it at once.
     *
     * ⛔ **UNANIMITY, AND THE NARROWNESS IS THE HONESTY.** The population is the
     * sweep's own — `GbpConnections::usableLocationIds()`, which is what
     * `gbp:sync` fans out over — and this returns a reason **only when every one
     * of those locations reports the same one**. A tenant with two locations
     * where one read cleanly and the other failed gets **no** account-level
     * sentence, because there is no sentence about *your Google listing* that is
     * true of both, and inventing a hedge (*"one of your locations…"*) on the
     * one screen a small business reads as a summary buys a worse sentence for
     * the single-location account, which is almost all of them.
     *
     * ⚠️ **SO IT UNDER-REPORTS AND NEVER OVER-CLAIMS, AND THE GAP IS NAMED
     * RATHER THAN GLOSSED**: a partially-failing multi-location account, and an
     * account with a mix of reasons, both read as though nothing were wrong.
     * ⛔ **The per-location answer is not lost** — the Google reviews screen
     * renders {@see self::reviewAbsence()} on each location card, which is where
     * that owner finds out which one, and it is one click from Home.
     *
     * ⚠️ **AN EMPTY POPULATION IS NULL RATHER THAN A REASON.** *"Nothing is
     * connected"* is a different sentence answered a rung earlier by
     * `ProofNumbers::notes()`, and answering it twice in two vocabularies is how
     * two ladders come to disagree.
     *
     * @param  array<int, int>  $locationIds
     */
    public function reviewAbsenceAcross(array $locationIds): ?ReviewSyncAbsenceReason
    {
        /** @var array<string, ReviewSyncAbsenceReason> $reasons */
        $reasons = [];

        foreach ($locationIds as $locationId) {
            $reason = $this->reviewAbsence($locationId);

            if ($reason === null) {
                return null;
            }

            $reasons[$reason->value] = $reason;
        }

        return count($reasons) === 1 ? array_values($reasons)[0] : null;
    }

    /**
     * A closed run's own account of itself.
     *
     * ⚠️ **IT READS TWO OUTCOMES WHERE `recordedSearchReason()` READS ONE.**
     * `SyncGoogleReviewsJob` writes `unavailable` from `execute()` and `handoff`
     * from `handoff()`, and both mean *no reviews were read*; a reader that knew
     * only about `unavailable` would report a handed-off run — the arm a
     * switched-off integration and an unconnected location both land on — as a
     * successful read.
     *
     * ⚠️ **AN UNRECOGNISED CODE IS NOT `null`**, on `recordedSearchReason()`'s
     * own rule and with more force here, because this vendor's reason vocabulary
     * is **Zernio's own `code` field** and grows without us.
     */
    private function recordedReviewReason(AutomationRun $run): ?ReviewSyncAbsenceReason
    {
        $output = is_array($run->output) ? $run->output : [];
        $outcome = $output['outcome'] ?? null;

        if ($outcome !== 'unavailable' && $outcome !== 'handoff') {
            return null;
        }

        return ReviewSyncAbsenceReason::fromRecordedCode($output['reason'] ?? null)
            ?? ReviewSyncAbsenceReason::LastReadFailed;
    }

    /**
     * A `Succeeded` run's own account of itself.
     *
     * ⚠️ **AN UNRECOGNISED CODE IS NOT `null`.** A `Succeeded` row whose outcome
     * is `unavailable` says the read did not produce numbers; if this enum has
     * not heard of its reason — an older deploy's spelling — the honest answer is
     * still *"we did not read it"*, and returning null would hand it to
     * `NoDataYet`, which is the defect this class exists to close.
     */
    private function recordedSearchReason(AutomationRun $run): ?VisibilityAbsenceReason
    {
        $output = is_array($run->output) ? $run->output : [];

        if (($output['outcome'] ?? null) !== 'unavailable') {
            return null;
        }

        return VisibilityAbsenceReason::fromRecordedCode($output['reason'] ?? null)
            ?? VisibilityAbsenceReason::LastReadFailed;
    }

    private function recordedCompetitorReason(AutomationRun $run): ?CompetitorAbsenceReason
    {
        $output = is_array($run->output) ? $run->output : [];

        if (($output['outcome'] ?? null) !== 'unavailable') {
            return null;
        }

        return CompetitorAbsenceReason::fromRecordedCode($output['reason'] ?? null)
            ?? CompetitorAbsenceReason::LastCheckFailed;
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

use App\Jobs\Visibility\SyncCompetitorSignalsJob;
use App\Services\Visibility\CompetitorSignals;

/**
 * Why this platform has no nearby-business comparison for a location — the
 * *reason* half of {@see CompetitorComparisonState::NotCheckedYet}
 * and {@see CompetitorComparisonState::Unavailable}.
 *
 * ⛔ **THE CASE THIS EXISTS FOR IS THE ONE THAT USED TO BE `NoNeighbours`**
 * (9820–9839). `CompetitorSignals::compare()` returned *"we have not found
 * nearby businesses"* on an empty `competitors` table, and that table is empty
 * for a location whose nightly sync has never run, for one whose sync hit **our
 * own daily Places cost ceiling**, for one whose sync was refused by a kill
 * switch, and for one that genuinely has no comparable neighbours. Four
 * populations, one sentence, and three of the four are facts about this
 * platform stated to a small business as a fact about their neighbourhood.
 *
 * ⛔ **THE JOB ALREADY KNEW AND THE READER DID NOT**, which is the true-sibling
 * tell: `SyncCompetitorSignalsJob::execute()` has carried *"'Could not ask
 * Google' and 'asked, nobody is nearby' must not close the same way"* since it
 * shipped, and recorded exactly that distinction on the run row — where nothing
 * read it.
 *
 * ⚠️ **`'no_ratings'` KEEPS ITS STRING.** It is the one value that existed
 * before this slice ({@see CompetitorSignals::compare()}),
 * and a value is not renamed while it is being typed.
 *
 * ## The partition
 *
 * Two questions, on {@see VisibilityAbsenceReason}'s argument —
 * except that **this enum has no owner-actionable case at all, and that is a
 * property rather than an oversight.** The one thing an owner can do about a
 * missing comparison is confirm their Google listing, and that is
 * {@see CompetitorComparisonState::NoPlaceId} — a *state*, reached
 * before any of these. So `theOwnerCanAct()` is deliberately **not** declared
 * here: a predicate that is false for every case of an enum is 256's shape, and
 * the honest form is its absence.
 */
enum CompetitorAbsenceReason: string
{
    /**
     * No competitor sync has ever run for this location.
     *
     * ⛔ **OURS.** `visibility:sync-competitors` is scheduled once a day, so a
     * location confirmed at nine in the morning waits until the small hours —
     * and on a deployment whose queue is not being consumed it waits for ever.
     */
    case NeverChecked = 'never_checked';

    /** The most recent check ended in the queue's `Failed`. Ours. */
    case LastCheckFailed = 'last_check_failed';

    /**
     * The most recent check was skipped before it started.
     *
     * ⚠️ **A SKIP IS NOT A FAILURE** ({@see AutomationRunStatus}),
     * and the attribution argument is
     * {@see VisibilityAbsenceReason::ReadingStopped}'s.
     */
    case CheckingStopped = 'checking_stopped';

    /**
     * Our own daily Places ceiling declined to spend.
     *
     * ⛔ **OURS, AND THE MOST EXPENSIVE ONE TO GET WRONG.**
     * `PlacesBudgetExhausted`'s own docblock says it *"is not an error … this is
     * the safeguard working"*, and `PlaceResolver` already words its half
     * honestly — *"We've used today's lookups"* (9755). Reporting it as an empty
     * neighbourhood is a cost cap of ours wearing a claim about somebody's high
     * street.
     */
    case LookupBudgetSpent = 'places_budget_exhausted';

    /**
     * Google answered our nearby search with a refusal about **us** — our key,
     * our project, our request.
     *
     * ⚠️ **OURS.** `PlacesRequestFailed`'s `unauthorized`, `unconfigured`,
     * `bad_request`, `quota` and any unclassified 4xx are all facts about this
     * platform's own Google Cloud project.
     */
    case LookupRefused = 'places_request_refused';

    /**
     * Google did not answer at all, or answered 5xx.
     *
     * The vendor's, and the only case here that is. `PlacesRequestFailed`'s
     * `unreachable` and `server_error`.
     */
    case LookupUnanswered = 'places_request_unanswered';

    /**
     * Neighbours were found and Google holds no rating for any of them.
     *
     * The vendor's — a fact about Google's data rather than about this platform
     * or this business. The pre-existing value, and the only one of these that
     * had a writer before this slice.
     */
    case NoRatings = 'no_ratings';

    /**
     * Whether this platform decided the absence out of its own configuration,
     * its own schedule and its own ledger.
     */
    public function isThisPlatformsOwnDoing(): bool
    {
        return match ($this) {
            self::NeverChecked, self::LastCheckFailed, self::CheckingStopped,
            self::LookupBudgetSpent, self::LookupRefused => true,
            self::LookupUnanswered, self::NoRatings => false,
        };
    }

    /**
     * What the owner is told, in place, on the visibility screen.
     *
     * ⚠️ **NONE OF THESE MAY IMPLY THE NEIGHBOURHOOD IS EMPTY**, which is the
     * whole subject; and the five that are ours say so, for
     * {@see VisibilityAbsenceReason::sentence()}'s reason.
     */
    public function sentence(): string
    {
        return match ($this) {
            self::NeverChecked => 'We have not checked for nearby businesses yet. '
                .'That is on us — nothing is missing from your listing.',
            self::LastCheckFailed => 'Our last check for nearby businesses did not finish. '
                .'That is on us, not on your listing — we will try again automatically.',
            self::CheckingStopped => 'Checking for nearby businesses is paused, so there is nothing to compare yet.',
            self::LookupBudgetSpent => "We have used today's Google lookups, so we could not compare with nearby "
                .'businesses. That is our own limit, not anything about your listing.',
            self::LookupRefused => 'We could not ask Google about nearby businesses. '
                .'That is on us, not on your listing — we will try again automatically.',
            self::LookupUnanswered => 'Google could not answer our request about nearby businesses. '
                .'We will try again automatically.',
            self::NoRatings => 'There are businesses near you, and Google has no ratings for them yet, '
                .'so there is nothing to average.',
        };
    }

    /**
     * The state this reason belongs under, so the two call sites cannot
     * disagree — {@see VisibilityAbsenceReason::state()}.
     */
    public function state(): CompetitorComparisonState
    {
        return match ($this) {
            self::NeverChecked, self::LastCheckFailed,
            self::CheckingStopped => CompetitorComparisonState::NotCheckedYet,
            self::LookupBudgetSpent, self::LookupRefused,
            self::LookupUnanswered, self::NoRatings => CompetitorComparisonState::Unavailable,
        };
    }

    /**
     * A code recorded by {@see SyncCompetitorSignalsJob},
     * or null when it is one this reading layer does not recognise.
     *
     * Fails open to null; the caller turns a null into
     * {@see self::LastCheckFailed}, never into "nobody is nearby".
     */
    public static function fromRecordedCode(mixed $code): ?self
    {
        return is_string($code) ? self::tryFrom($code) : null;
    }
}

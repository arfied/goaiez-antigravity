<?php

declare(strict_types=1);

namespace App\Enums;

use App\Exceptions\SearchConsoleRequestFailed;
use App\Jobs\Visibility\SyncSearchConsoleJob;
use App\Services\Visibility\VisibilityReading;

/**
 * Why this platform has no Google Search numbers for a window — the *reason*
 * half of {@see VisibilityState::NotReadYet} and {@see VisibilityState::Unavailable}.
 *
 * ⛔ **A TYPE RATHER THAN THE FIVE BARE STRINGS {@see VisibilityReading}'s
 * DOCBLOCK PROMISED, BECAUSE THE PROMISE WAS NOT TRUE IN THREE SEPARATE WAYS**
 * (9820–9839). That docblock said *"$reason is a short code —
 * 'connection_revoked', 'property_forbidden', 'quota_exhausted', 'vendor_error',
 * 'property_not_verified'. Each maps to a different sentence and a different
 * remedy, and collapsing them is decision 532's trap"*, and:
 *
 *   1. **`VisibilityReadings::read()` could only ever produce one of them** —
 *      `'connection_revoked'` — because the other four are classified by
 *      `SearchConsoleRequestFailed::readingReason()` inside
 *      `SyncSearchConsoleJob`, which writes them to `automation_runs.output`
 *      and nowhere the reading layer ever looked.
 *   2. **One of the five is not a value anything produces.**
 *      `'property_not_verified'` is `GscPermissionLevel::UnverifiedUser`'s
 *      string, a different subject one file over; `readingReason()`'s fifth
 *      value is `'property_missing'`, which the list did not carry.
 *   3. **The only owner-facing renderer collapsed all of them anyway** —
 *      `visibility.blade.php`'s *"Search numbers are temporarily unavailable."*
 *      — so the promised five sentences were one sentence, and the field's
 *      single reader was an artisan command.
 *
 * ⚠️ **THE VALUES ARE THE STRINGS THAT WERE ALREADY THERE**, on
 * {@see FetchRefusalReason}'s rule: `readingReason()` writes them into
 * `automation_runs.output.reason`, that column is jsonb rather than a CHECK
 * list, and rows written before this slice must go on resolving. Nothing is
 * renamed while it is being typed. The four new cases are new strings.
 *
 * ## The partition, and why it is not {@see FetchRefusalReason}'s
 *
 * 9741 partitions a fetch refusal three ways — *ours*, *theirs* (the fetched
 * origin's own rule), *nobody's*. ⛔ **That trichotomy does not transfer, and
 * the reason is that this surface has a THIRD party the fetch path does not**:
 * the account holder. A tenant whose Search Console grant was revoked, whose
 * Google account lost permission on the property, or whose property is gone can
 * *act*; Google having a bad afternoon is nobody's to act on; and our own
 * unrun sweep is ours. So the two questions asked here are
 * {@see self::isThisPlatformsOwnDoing()} and {@see self::theOwnerCanAct()},
 * with the residual — neither ours nor theirs — being the vendor's.
 *
 * ⚠️ **AND THE STATE AND THE REASON ANSWER DIFFERENT QUESTIONS, WHICH IS
 * 9741's LESSON GENERALISED.** The state answers *did we get an answer out of
 * Google*; the reason answers *whose doing is that*. They are orthogonal on
 * purpose, which is why {@see self::QuotaExhausted} is ours and still sits under
 * `Unavailable`: we asked, and our own ceiling is what said no.
 */
enum VisibilityAbsenceReason: string
{
    /**
     * No sync has ever read this location's Search performance.
     *
     * ⛔ **OURS, AND THE CASE THIS SLICE EXISTS FOR.** `visibility.blade.php`
     * said *"Google Search has nothing to report for this period yet"* over a
     * population that included a location connected an hour ago, a stopped
     * queue, and a `gsc:sync` nobody had run — three facts about this platform,
     * told to a small business as a fact about how many people are looking for
     * them.
     */
    case NeverRead = 'never_read';

    /**
     * The most recent read of this location ended in the queue's `Failed`.
     *
     * Ours, and this carries nothing of the exception either way.
     * ⚠️ **`automation_runs.error` NO LONGER CARRIES THE MESSAGE — 11457.** It
     * did when this was written; since 11330 it holds the exception CLASS for
     * everything but our own `MailNotDeliverable`, because an SMTP refusal
     * named an account holder's own address. **The rule this states is
     * unchanged and its subject narrowed**; `failed_jobs.exception` is where
     * the full text lands now.
     */
    case LastReadFailed = 'last_read_failed';

    /**
     * The most recent read was skipped before it started — a kill switch, a
     * suspension, or the owner's own pause.
     *
     * ⚠️ **A SKIP IS NOT A FAILURE AND MUST NEVER BE REPORTED AS ONE**
     * ({@see AutomationRunStatus}'s own docblock), which is why this is a case
     * rather than a fold into {@see self::LastReadFailed}.
     *
     * ⚠️ **ATTRIBUTED TO THIS PLATFORM THOUGH ONE OF ITS FOUR CAUSES IS THE
     * OWNER'S.** Three are ours — the config kill switch, a compliance
     * suspension, an unreachable per-automation toggle — and the fourth is the
     * owner's own pause, which they set and can see. Attributing the whole case
     * to us is the conservative direction: the cost of calling our kill switch
     * the owner's doing is a false accusation, and the cost of calling their
     * pause ours is a sentence that is merely incomplete.
     */
    case ReadingStopped = 'reading_stopped';

    /**
     * We have read this location, and never as far back as this window.
     *
     * ⛔ **OURS, AND IT IS NOT A TRANSIENT.** `config('gsc.lookback_days')` is
     * 30, so one sync covers the last thirty days and no more; a location
     * connected three weeks ago has **never been asked** about the window
     * `LocalVisibility::for()` uses as the *earlier* half of its movement
     * sentence, and it never will be. Without this case that window reads as
     * `NoDataYet` when empty and — worse — as `Measured` over its two
     * overlapping days when not, so a twenty-eight-day total was compared
     * against a two-day one and the screen said *"Fewer people found you in
     * Google Search this month"* about a decline that never happened.
     */
    case PeriodBeforeFirstRead = 'period_before_first_read';

    /**
     * The grant died and the owner has to re-authorise Google.
     *
     * Theirs to act on. Produced by `VisibilityReadings::read()` from
     * `ConnectionState::Unusable` and by `SearchConsoleRequestFailed` from a
     * revoking 401.
     */
    case ConnectionRevoked = 'connection_revoked';

    /**
     * This Google account does not hold a permission on that property.
     *
     * Theirs to act on, and decision 532's trap is exactly here: classifying it
     * as a revoked connection sends an owner to re-authorise something that was
     * never broken.
     */
    case PropertyForbidden = 'property_forbidden';

    /** The property is no longer in Search Console. Theirs to act on. */
    case PropertyMissing = 'property_missing';

    /**
     * A quota wall.
     *
     * ⚠️ **OURS**, on `TokenService`'s own ruling — *"4xx + a quota reason:
     * `quota_exhausted`. Ours"* — and it is the case that proves the state and
     * the reason are two questions: we asked and got an answer, so the state is
     * `Unavailable`, and what answered was our own ceiling.
     */
    case QuotaExhausted = 'quota_exhausted';

    /** Google could not answer. Neither ours nor the owner's. */
    case VendorError = 'vendor_error';

    /**
     * Whether this platform decided the absence out of its own configuration,
     * its own schedule and its own ledger.
     *
     * ⛔ **THE QUESTION A CALLER RENDERING AN ABSENCE TO A PERSON OWES FIRST**
     * (9755). Every `true` below is a sentence that must not be told to a small
     * business as a fact about their listing or about how many people are
     * searching for them.
     */
    public function isThisPlatformsOwnDoing(): bool
    {
        return match ($this) {
            self::NeverRead, self::LastReadFailed, self::ReadingStopped,
            self::PeriodBeforeFirstRead, self::QuotaExhausted => true,
            self::ConnectionRevoked, self::PropertyForbidden,
            self::PropertyMissing, self::VendorError => false,
        };
    }

    /**
     * Whether the account holder can do something about it.
     *
     * ⛔ **NOT THE COMPLEMENT OF {@see self::isThisPlatformsOwnDoing()}**, which
     * is 9741's finding in this file's vocabulary: a `false` there covers the
     * owner's revoked grant **and** Google being down, and those are not the
     * same fact. The residual — neither ours nor theirs — is the vendor's.
     */
    public function theOwnerCanAct(): bool
    {
        return match ($this) {
            self::ConnectionRevoked, self::PropertyForbidden, self::PropertyMissing => true,
            self::NeverRead, self::LastReadFailed, self::ReadingStopped,
            self::PeriodBeforeFirstRead, self::QuotaExhausted, self::VendorError => false,
        };
    }

    /**
     * What the owner is told, in place, on the visibility screen.
     *
     * ⚠️ **THE SENTENCES FOR {@see self::isThisPlatformsOwnDoing()} SAY SO OUT
     * LOUD** rather than merely omitting the cause. *"Search numbers are
     * temporarily unavailable"* is not false and it is not the fix: an owner
     * reading it has no way to know that nothing about their business is
     * involved, and the one action it suggests — go and look at Google — is the
     * wrong one. `22`'s outcome-language rule cuts the other way here, because
     * what this person controls on four of these is nothing at all, and saying
     * so is the outcome.
     *
     * ⚠️ **NO CADENCE IS NAMED.** *"Tonight"* and *"in the morning"* are facts
     * about `routes/console.php`, which is not this file and moves without it.
     */
    public function sentence(): string
    {
        return match ($this) {
            self::NeverRead => 'We have not read your Google Search data for this location yet. '
                .'That is on us, not on Google — the first read happens automatically.',
            self::LastReadFailed => 'Our last read of your Google Search data did not finish. '
                .'That is on us, not on your listing — we will try again automatically.',
            self::ReadingStopped => 'Reading your Google Search data is paused, so this period has not been read.',
            self::PeriodBeforeFirstRead => 'We had not started reading your Google Search data this far back, '
                .'so there is nothing here to compare. That is on us, not on your listing.',
            self::ConnectionRevoked => 'Reconnect Google Search Console under Google reviews to see search movement again.',
            self::PropertyForbidden => 'This Google account no longer has access to the Search Console property '
                .'for this location. Restore its access, or choose a different property.',
            self::PropertyMissing => 'That Search Console property is no longer there. Choose a different one for this location.',
            self::QuotaExhausted => 'We have used up our own allowance for reading Google Search today. '
                .'That is on us, not on your listing — we will try again automatically.',
            self::VendorError => 'Google could not answer our last request for this data. '
                .'Nothing is wrong with your listing — we will try again automatically.',
        };
    }

    /**
     * The state this reason belongs under.
     *
     * ⛔ **HERE RATHER THAN AT THE TWO CALL SITES, SO THEY CANNOT DISAGREE.**
     * {@see SearchConsoleRequestFailed::readingReason()}'s own argument — *"the mapping is here rather than
     * at the call site so two callers cannot disagree about what a 403 means"* —
     * applied to the pairing instead of to the code. A reason minted under the
     * wrong state renders under the wrong sentence, and nothing else in the
     * pipeline would notice.
     */
    public function state(): VisibilityState
    {
        return match ($this) {
            self::NeverRead, self::LastReadFailed, self::ReadingStopped,
            self::PeriodBeforeFirstRead => VisibilityState::NotReadYet,
            self::ConnectionRevoked, self::PropertyForbidden, self::PropertyMissing,
            self::QuotaExhausted, self::VendorError => VisibilityState::Unavailable,
        };
    }

    /**
     * A code recorded by {@see SyncSearchConsoleJob}, or
     * null when it is one this reading layer does not recognise.
     *
     * ⚠️ **FAILS OPEN TO NULL RATHER THAN THROWING, AND THE CALLER TURNS A NULL
     * INTO {@see self::LastReadFailed}.** The input is a jsonb value written by
     * whatever version of that job was deployed when the row was made, and a
     * page render is not the place to discover that an old row spells something
     * this enum has not heard of. What must not happen is the opposite failure —
     * an unrecognised code rendering as `NoDataYet`, which is the false absence
     * this whole slice is about.
     */
    public static function fromRecordedCode(mixed $code): ?self
    {
        return is_string($code) ? self::tryFrom($code) : null;
    }
}

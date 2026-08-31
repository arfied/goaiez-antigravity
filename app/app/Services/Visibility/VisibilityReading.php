<?php

declare(strict_types=1);

namespace App\Services\Visibility;

use App\Enums\VisibilityAbsenceReason;
use App\Enums\VisibilityState;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * What we can honestly say about one location's Google Search visibility over
 * one window.
 *
 * WHY THIS IS A TYPE AND NOT A NULLABLE INT. Decision 1084, verbatim: *"the gate
 * is unwinnable any other way"*. `28` §5.3.4 requires the product to *"say
 * plainly"* what is unavailable and to *"never imply the number is zero"*, and
 * `?int` cannot distinguish *we asked and there were none* from *we cannot ask*
 * from *we have not asked yet*. The three have three different remedies and one
 * of them is not the owner's problem at all.
 *
 * WHY THE CONSTRUCTOR IS PRIVATE. Decision 285's argument, applied to a reading
 * rather than a permit: a forgeable "measured" reading is decoration. One
 * factory per {@see VisibilityState} case, and none of them can be called
 * without stating which state is being claimed. `VisibilityReadings` is the only
 * production caller and a lint holds it there.
 *
 * WHY {@see totals()} THROWS RATHER THAN RETURNING NULL. Returning
 * `?VisibilityTotals` rebuilds the defect one level out — the caller writes
 * `$reading->totals()?->clicks ?? 0` and the zero is back, printed under a
 * business's name as a claim that nobody found them. Throwing means the only way
 * to reach a number is to have checked the state, and the check is a `match` the
 * compiler and Larastan can both see is exhaustive.
 *
 * WHY {@see $reason} IS A TYPE AND NOT A SHORT CODE.
 *
 * ⛔ **IT WAS A `?string` AND ITS DOCBLOCK PROMISED FIVE CODES "EACH MAPPING TO
 * A DIFFERENT SENTENCE AND A DIFFERENT REMEDY", AND ALL THREE HALVES OF THAT
 * WERE FALSE** (9820–9839). {@see VisibilityReadings::read()} could only ever
 * produce **one** of the five; one of the five — `'property_not_verified'` — was
 * a value from `GscPermissionLevel`, a different subject, and the code that
 * actually exists (`'property_missing'`) was not on the list; and the one
 * owner-facing renderer collapsed the whole state into *"Search numbers are
 * temporarily unavailable."* The field's only reader was an artisan command.
 * **A protection asserted in a docblock and implemented nowhere is
 * `docs/FAILURE-SHAPES.md`'s *a protection layer asserted before it is true*.**
 *
 * {@see VisibilityAbsenceReason} is the type, its `match`es have no default, and
 * it carries the sentence and the attribution rather than leaving both to
 * whichever surface renders next.
 *
 * ⚠️ **THE OLD RULE SURVIVES INSIDE IT**: still a short code, never a vendor
 * response body, never a sentence built from one, and never the site property —
 * `ProviderRequestFailed` states the same rule for the same reason.
 */
final class VisibilityReading
{
    private function __construct(
        public readonly VisibilityState $state,
        public readonly CarbonImmutable $windowStart,
        public readonly CarbonImmutable $windowEnd,
        private readonly ?VisibilityTotals $totals = null,
        public readonly ?VisibilityAbsenceReason $reason = null,
    ) {}

    /**
     * No Search Console connection for this tenant.
     */
    public static function notConnected(CarbonImmutable $start, CarbonImmutable $end): self
    {
        return new self(VisibilityState::NotConnected, $start, $end);
    }

    /**
     * Connected, but this location has no site property mapped to it.
     */
    public static function noPropertyChosen(CarbonImmutable $start, CarbonImmutable $end): self
    {
        return new self(VisibilityState::NoPropertyChosen, $start, $end);
    }

    /**
     * Asked, answered, nothing there.
     *
     * ⚠️ Distinct from `measured()` with zeroes, and the distinction is the point.
     * A window Google has no rows for is not a window in which zero people found
     * the business — it is a window Google has nothing to say about, which for a
     * new property is the expected state for weeks (`28` §5.3.5).
     *
     * ⛔ **AND DISTINCT FROM "WE HAVE NOT ASKED", WHICH IS WHAT IT USED TO
     * ANSWER FOR** (9820–9839). {@see VisibilityReadings::read()} may reach this
     * only from a recorded read that covered the window; everything else is
     * {@see self::notReadYet()}. **An empty query against our own snapshot table
     * is not evidence that Google was asked.**
     */
    public static function noDataYet(CarbonImmutable $start, CarbonImmutable $end): self
    {
        return new self(VisibilityState::NoDataYet, $start, $end);
    }

    /**
     * This platform has not read the window, and the reason is ours.
     *
     * ⚠️ **THE REASON IS REQUIRED AND ITS STATE IS ASSERTED.** A reason minted
     * under the wrong state renders under the wrong sentence and nothing else in
     * the pipeline would notice, so {@see VisibilityAbsenceReason::state()} owns
     * the pairing and this refuses anything that disagrees with it.
     */
    public static function notReadYet(
        CarbonImmutable $start,
        CarbonImmutable $end,
        VisibilityAbsenceReason $reason,
    ): self {
        if ($reason->state() !== VisibilityState::NotReadYet) {
            throw new LogicException(
                "Not a NotReadYet reason: {$reason->value} belongs under {$reason->state()->value}."
            );
        }

        return new self(VisibilityState::NotReadYet, $start, $end, null, $reason);
    }

    public static function measured(
        CarbonImmutable $start,
        CarbonImmutable $end,
        VisibilityTotals $totals,
    ): self {
        return new self(VisibilityState::Measured, $start, $end, $totals);
    }

    /**
     * We asked and did not get numbers back.
     *
     * Each reason maps to a different sentence and a different remedy, and
     * collapsing them is decision 532's trap: *"a 403 means two opposite
     * things"*, where classifying a billing problem as a revoked connection
     * sends every owner to re-authorise something that was never broken.
     *
     * ⛔ **THAT SENTENCE WAS HERE BEFORE THIS SLICE AND THE RENDERER COLLAPSED
     * THEM ANYWAY** (9820–9839). What makes it true now is
     * {@see VisibilityAbsenceReason::sentence()} plus a `match` with no default
     * at the one owner-facing surface.
     *
     * ⚠️ **THE STATE PAIRING IS ASSERTED**, for {@see self::notReadYet()}'s
     * reason: `QuotaExhausted` is ours and belongs here because we *asked*, and
     * `NeverRead` is ours and does not.
     */
    public static function unavailable(
        CarbonImmutable $start,
        CarbonImmutable $end,
        VisibilityAbsenceReason $reason,
    ): self {
        if ($reason->state() !== VisibilityState::Unavailable) {
            throw new LogicException(
                "Not an Unavailable reason: {$reason->value} belongs under {$reason->state()->value}."
            );
        }

        return new self(VisibilityState::Unavailable, $start, $end, null, $reason);
    }

    public function isMeasured(): bool
    {
        return $this->state === VisibilityState::Measured;
    }

    /**
     * The numbers, or a crash.
     *
     * @throws LogicException when the reading is not `Measured`. That is a
     *                        programming error rather than a runtime condition —
     *                        every state is reachable and every caller has a
     *                        `match` available to distinguish them, so arriving
     *                        here means somebody assumed.
     */
    public function totals(): VisibilityTotals
    {
        if ($this->totals === null) {
            throw new LogicException(
                'No visibility totals: this reading is '.$this->state->value.'. '
                .'Check the state before reading the numbers — decision 1084 exists '
                .'because the alternative prints a zero nobody measured.'
            );
        }

        return $this->totals;
    }
}

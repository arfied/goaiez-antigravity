<?php

declare(strict_types=1);

namespace App\Services\Visibility;

use App\Enums\CompetitorAbsenceReason;
use App\Enums\CompetitorComparisonState;
use LogicException;

/**
 * Aggregate nearby-competitor reading for the Normal surface (decision 196).
 *
 * Names never appear here. The Advanced table reads the models directly.
 *
 * ⛔ **{@see $reason} WAS A `?string` WITH EXACTLY ONE WRITER AND NO READER
 * ANYWHERE IN `app/` OR `resources/`** (9820–9839) — 272's shape, a control that
 * looked like a control. It is now {@see CompetitorAbsenceReason}, it carries
 * the owner-facing sentence and the attribution, and the screen renders it.
 */
final class CompetitorComparison
{
    /**
     * $neighbourCount is every peer in the set; $ratedCount is the subset the
     * average is actually computed from. They differ whenever Google has no
     * rating for a peer, and conflating them is how a sentence claims a wider
     * basis than it has.
     */
    private function __construct(
        public readonly CompetitorComparisonState $state,
        public readonly ?int $neighbourCount = null,
        public readonly ?int $ratedCount = null,
        public readonly ?float $averageRating = null,
        public readonly ?float $ourRating = null,
        public readonly ?CompetitorAbsenceReason $reason = null,
    ) {}

    public static function measured(
        int $neighbourCount,
        int $ratedCount,
        float $averageRating,
        ?float $ourRating,
    ): self {
        return new self(
            CompetitorComparisonState::Measured,
            neighbourCount: $neighbourCount,
            ratedCount: $ratedCount,
            averageRating: $averageRating,
            ourRating: $ourRating,
        );
    }

    public static function noPlaceId(): self
    {
        return new self(CompetitorComparisonState::NoPlaceId);
    }

    /**
     * We asked Google, and there is nobody comparable nearby.
     *
     * ⛔ **NOT MINTABLE FROM AN EMPTY TABLE ANY MORE** (9820–9839).
     * {@see CompetitorSignals::compare()} reaches this
     * only from a `visibility.competitor_signals` run that recorded reaching
     * Google; every other empty is {@see self::absent()}.
     */
    public static function noNeighbours(): self
    {
        return new self(CompetitorComparisonState::NoNeighbours);
    }

    /**
     * We have no comparison, and this is why.
     *
     * ⚠️ **THE STATE COMES FROM THE REASON RATHER THAN FROM THE CALLER**, so a
     * *"we have not checked"* cannot be minted under *"we checked and could
     * not"* by a caller that passed the wrong factory. Same argument as
     * {@see VisibilityReading::notReadYet()}, and this
     * enum's pairing is small enough that one factory covers both states.
     */
    public static function absent(CompetitorAbsenceReason $reason): self
    {
        return new self($reason->state(), reason: $reason);
    }

    public function isMeasured(): bool
    {
        return $this->state === CompetitorComparisonState::Measured;
    }

    /**
     * §5.5 Normal sentence — aggregate and unnamed.
     *
     * @throws LogicException when not Measured
     */
    public function sentence(): string
    {
        if (! $this->isMeasured()) {
            throw new LogicException(
                'No competitor sentence: this comparison is '.$this->state->value.'.'
            );
        }

        $avg = number_format((float) $this->averageRating, 1);
        // The rated count, not the neighbour count — this number is the basis of
        // the average standing right next to it, and the two must agree.
        $count = (int) $this->ratedCount;

        if ($this->ourRating === null) {
            return "Businesses like yours nearby average {$avg}★ ({$count} rated).";
        }

        $ours = number_format($this->ourRating, 1);

        return "Businesses like yours nearby average {$avg}★. You are at {$ours}★.";
    }

    /**
     * What the owner is told when there is no comparison — the counterpart of
     * {@see self::sentence()}, and null on the two states that carry no reason.
     *
     * ⚠️ **`NoPlaceId` AND `NoNeighbours` DELIBERATELY RETURN NULL HERE.** They
     * are answers rather than absences with a cause: one is the owner's to act
     * on and the other is a measurement. Their copy stays on the screen, which
     * is where the *"confirm your listing"* call to action belongs.
     */
    public function absenceSentence(): ?string
    {
        return $this->reason?->sentence();
    }
}

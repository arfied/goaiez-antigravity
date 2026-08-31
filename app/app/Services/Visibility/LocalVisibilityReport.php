<?php

declare(strict_types=1);

namespace App\Services\Visibility;

/**
 * One location's Local Visibility pack for the Normal owner surface.
 */
final readonly class LocalVisibilityReport
{
    public function __construct(
        public VisibilityReading $search,
        public VisibilityMovement $searchMovement,
        public VisibilityReading $earlierSearch,
        public CompetitorComparison $competitors,
        public bool $mapsImpressionsAvailable,
        public bool $catchmentAvailable,
        public string $mapsUnavailableReason,
        public string $catchmentUnavailableReason,
    ) {}

    /**
     * §5.3.3 movement sentence when both windows are Measured; otherwise null.
     */
    public function searchSentence(): ?string
    {
        if (! $this->search->isMeasured() || $this->searchMovement === VisibilityMovement::Indeterminate) {
            return null;
        }

        $now = $this->search->totals()->impressions;
        $before = $this->earlierSearch->totals()->impressions;

        // Indeterminate is refused above, so PHPStan narrows to the three
        // measurable movements here — leave that case out of the match.
        return match ($this->searchMovement) {
            VisibilityMovement::Up => "More people found you in Google Search this month — {$now} up from {$before}.",
            VisibilityMovement::Down => "Fewer people found you in Google Search this month — {$now} down from {$before}.",
            VisibilityMovement::Unchanged => "About the same number of people found you in Google Search this month — {$now}.",
        };
    }

    public function mapsUnavailableSentence(): string
    {
        return 'Maps impressions are unavailable — Google Business Profile performance is not connected yet.';
    }

    public function catchmentUnavailableSentence(): string
    {
        return 'A map of where visitors come from is not available yet. We never show an empty map as a finding.';
    }
}

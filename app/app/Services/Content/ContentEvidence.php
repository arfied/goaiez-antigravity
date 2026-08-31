<?php

declare(strict_types=1);

namespace App\Services\Content;

/**
 * What a candidate page offers in support of itself.
 *
 * ⚠️ **IT ARRIVES WITH THE CANDIDATE AND IS NEVER FETCHED HERE** (`BUILD-PLAN`
 * §2.11.5 conflict 5). The Orchestrator's DIAGNOSE and the content engine that
 * assembles this are Stage 5; slice C gates what it is handed, and a gate that
 * went and found its own evidence would be the engine it is supposed to be
 * separate from.
 */
final readonly class ContentEvidence
{
    /**
     * @param  list<FirstPartyDatum>  $firstPartyData
     * @param  list<DemandSignal>  $demand
     */
    public function __construct(
        public array $firstPartyData = [],
        public array $demand = [],
    ) {}

    /**
     * The data points that are genuinely in the copy — see
     * {@see FirstPartyDatum::appearsIn()} for why a declaration is not enough.
     *
     * @return list<FirstPartyDatum>
     */
    public function cited(PageCopy $copy): array
    {
        $text = $copy->fullText();

        return array_values(array_filter(
            $this->firstPartyData,
            static fn (FirstPartyDatum $datum): bool => $datum->appearsIn($text),
        ));
    }

    /**
     * @return list<DemandSignal>
     */
    public function realDemand(): array
    {
        return array_values(array_filter(
            $this->demand,
            static fn (DemandSignal $signal): bool => $signal->isEvidence(),
        ));
    }
}

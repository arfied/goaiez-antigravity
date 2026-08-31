<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\DemandSource;

/**
 * Evidence that somebody actually asks the question a page answers — doc `16`
 * §15.3: *"answers a question with demonstrated real demand (support inbox, GSC
 * query, or keyword volume)"*.
 *
 * ⛔ **THE GATE ASKS FOR EVIDENCE, NOT FOR A VOLUME** — see
 * {@see DemandSource}. Nothing in `16`, `29` or `33` states how many people
 * asking is enough, and `CLAUDE.md` forbids inventing a figure that would read
 * as policy for ever after.
 */
final readonly class DemandSignal
{
    public function __construct(
        public DemandSource $source,
        /** The question or query, as it was actually asked. */
        public string $query,
        /** How many times. */
        public int $count,
    ) {}

    /**
     * Whether this is evidence at all.
     *
     * ⚠️ **A ZERO COUNT IS NOT WEAK EVIDENCE, IT IS THE ABSENCE OF ANY** (229).
     * A signal reporting that nobody asked is exactly the silence this check
     * exists to catch, and admitting it would make the check pass for every
     * candidate that bothered to attach an empty row.
     */
    public function isEvidence(): bool
    {
        return $this->count > 0 && trim($this->query) !== '';
    }

    /**
     * The shape written to `content_quality_checks.demand_evidence`.
     *
     * @return array{source: string, query: string, count: int}
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source->value,
            'query' => $this->query,
            'count' => $this->count,
        ];
    }
}

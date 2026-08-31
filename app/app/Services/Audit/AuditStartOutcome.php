<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\PublicAudit;
use App\Services\Places\PlaceCandidate;

/**
 * What happened when someone asked for an audit: one of exactly three things.
 *
 * Modelled as an outcome object rather than as an exception-plus-return because
 * two of the three are ordinary, expected answers. An ambiguous business name is
 * not an error — "there are four Starbucks near you, which one?" is the system
 * working — and neither is an exhausted budget, which `29` §11.2 row 2's gate
 * requires to "degrade, never throw at the visitor". Only the third is a
 * `started`.
 *
 * Shaped after ResolutionOutcome deliberately, so the two layers of this path
 * read the same way.
 */
final readonly class AuditStartOutcome
{
    /**
     * @param  list<PlaceCandidate>  $candidates
     */
    private function __construct(
        public ?PublicAudit $audit = null,
        public array $candidates = [],
        public ?string $reason = null,
    ) {}

    /**
     * One place, and an audit now exists for it — freshly queued, or a copy of
     * a recent one for the same place.
     */
    public static function started(PublicAudit $audit): self
    {
        return new self(audit: $audit);
    }

    /**
     * Several places matched. Nothing was created and nothing further was spent;
     * the visitor picks one and comes back with a `place_id`.
     *
     * @param  list<PlaceCandidate>  $candidates
     */
    public static function ambiguous(array $candidates): self
    {
        return new self(candidates: $candidates);
    }

    /**
     * No audit could be started, for a named reason the caller turns into a
     * sentence. Never a stack trace on a marketing page.
     */
    public static function unavailable(string $reason): self
    {
        return new self(reason: $reason);
    }

    public function isStarted(): bool
    {
        return $this->audit instanceof PublicAudit;
    }

    public function isAmbiguous(): bool
    {
        return $this->candidates !== [];
    }
}

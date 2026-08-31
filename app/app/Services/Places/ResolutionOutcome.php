<?php

declare(strict_types=1);

namespace App\Services\Places;

/**
 * What the resolver made of a pasted link.
 *
 * Three shapes, because `24` §1.2 has three endings and flattening them loses
 * the one that matters most:
 *
 *   resolved     one candidate — show the confirm card (§1.2.3)
 *   ambiguous    several — "show the owner the choices" (§1.2.1 row 5)
 *   unresolved   none — offer the ladder in §1.2.4, never a dead end
 *
 * `unresolved` carries a short reason code so the UI can say the true thing:
 * a rejected host is not the same as a link that resolved to a place we could
 * not find, and "try a different link" is only useful advice for one of them.
 */
final readonly class ResolutionOutcome
{
    /**
     * @param  list<PlaceCandidate>  $candidates
     */
    private function __construct(
        public array $candidates,
        public ?string $reason = null,
    ) {}

    public static function resolved(PlaceCandidate $candidate): self
    {
        return new self([$candidate]);
    }

    /**
     * @param  list<PlaceCandidate>  $candidates
     */
    public static function ambiguous(array $candidates): self
    {
        return new self($candidates);
    }

    /**
     * $reason is a short code — 'host_not_allowed', 'no_pattern_matched',
     * 'nothing_found', 'budget_exhausted'. Never a sentence and never the pasted
     * URL: these are logged.
     */
    public static function unresolved(string $reason): self
    {
        return new self([], $reason);
    }

    public function isResolved(): bool
    {
        return count($this->candidates) === 1;
    }

    public function isAmbiguous(): bool
    {
        return count($this->candidates) > 1;
    }

    public function candidate(): ?PlaceCandidate
    {
        return $this->isResolved() ? $this->candidates[0] : null;
    }
}

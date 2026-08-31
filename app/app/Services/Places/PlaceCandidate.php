<?php

declare(strict_types=1);

namespace App\Services\Places;

use App\Enums\PlaceResolutionRule;

/**
 * A place we believe the pasted link refers to — believed, not saved.
 *
 * `24` §1.2.3 is the reason this is a separate type from anything persisted:
 * "**Never save a resolved `place_id` without that confirmation**, however
 * confident the match. A wrong `place_id` invites real customers to review a
 * stranger's business, and every invite after it is wrong in the same direction."
 *
 * So the resolver returns candidates and nothing else. There is no method on
 * this class that writes, and the only thing that persists a `place_id` takes a
 * confirmation as an argument. The type system carries the rule.
 */
final readonly class PlaceCandidate
{
    /**
     * @param  list<string>  $categories
     *                                    Google's own categories for this place, primary first — the
     *                                    shape `PlaceSummary::categories()` produces and
     *                                    `TenantClassification::forCategories()` reads.
     *
     * ⚠️ EMPTY IS A REAL STATE AND IS NOT "NOT A HEALTH BUSINESS". Only the
     * ladder's search rungs have categories to carry: row 1 reads a `placeid=`
     * straight out of the URL and makes no request at all, so its candidate has
     * none, and a budget-exhausted or failed search produces no candidate at all.
     * A consumer must therefore treat `[]` as *no signal* and never as a signal
     * of absence — `TenantClassification` answers `Pii` for an empty list, which
     * is right at provisioning (decision 468) and would be wrong here as a reason
     * to move a business that some other signal already classified. See
     * `PlaceConfirmation::confirm()`, which only ever raises.
     */
    public function __construct(
        public string $placeId,
        public PlaceResolutionRule $rule,
        public ?string $displayName = null,
        public ?string $formattedAddress = null,
        public ?string $googleCid = null,
        public array $categories = [],
    ) {}

    /**
     * The confirm card of `24` §1.2.3 — "We found: Bartlett Plumbing, 1234 Union
     * Ave, Memphis, TN 38104".
     *
     * Null when we have an id but nothing human-readable to show, which is a
     * state the UI must handle rather than paper over: asking someone to confirm
     * an opaque identifier is not asking them to confirm anything.
     */
    public function confirmationLabel(): ?string
    {
        $parts = array_filter([$this->displayName, $this->formattedAddress]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    public function isConfirmable(): bool
    {
        return $this->confirmationLabel() !== null;
    }
}

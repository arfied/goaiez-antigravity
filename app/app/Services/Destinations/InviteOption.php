<?php

declare(strict_types=1);

namespace App\Services\Destinations;

use App\Enums\ReviewDestination;

/**
 * One button on the post-submit picker.
 *
 * THE `url` IS OURS, NOT THE PLATFORM'S. It points at `feedback.destination` —
 * our own redirect — because a destination click has to be recorded server-side
 * (decision 113 and `17` FPR-04b) and BUILD-PLAN §2.6.3 requires this picker to
 * work with JavaScript disabled. A `fetch()` beacon fired alongside a direct
 * link satisfies neither: with JavaScript off it records nothing at all, and the
 * hole is silent, because the customer's journey looks identical either way.
 *
 * The platform's real URL is resolved on the far side of that redirect, by
 * DestinationSettings::linkFor(), and never rendered into this page. That is
 * what keeps the host allowlist on the read path where decision 314–316 put it:
 * a link this object carried would have to be re-validated by whoever rendered
 * it, and that is precisely the check people forget.
 */
final readonly class InviteOption
{
    public function __construct(
        public ReviewDestination $destination,
        public string $url,
    ) {}

    /**
     * The platform's own name, for the button face.
     */
    public function label(): string
    {
        return $this->destination->label();
    }

    /**
     * Whether this is the one shown first and largest.
     *
     * `17` FPR-04b: "Google primed first, then remaining destinations shown
     * together". Primed means visual weight and position, never exclusivity —
     * every other destination is on the same screen, because `24` §2.3.3's
     * finding is that the customer does not come back.
     */
    public function isPrimed(): bool
    {
        return $this->destination === ReviewDestination::Google;
    }
}

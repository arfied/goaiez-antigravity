<?php

declare(strict_types=1);

namespace App\Services\Crm;

/**
 * What the Never-contact control may say and do for one contact.
 *
 * Three fields rather than a boolean, because the control has three states and
 * a boolean can only show two. The one a boolean loses is the one that matters:
 * *on, and not yours to turn off.*
 */
final readonly class NeverContactState
{
    public function __construct(
        /** Whether anything of ours currently refuses to message this contact. */
        public bool $on,
        /** Whether the owner may turn it off again. */
        public bool $releasable,
        /** Why not, in words the owner can act on, or null when they may. */
        public ?string $refusal = null,
    ) {}
}

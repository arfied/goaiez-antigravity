<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * One entry in the admin navigation.
 *
 * Each item names the ability it needs, and the nav asks the Gate — it never
 * compares a role. That keeps AdminAccess the single place authorization is
 * decided, and means a nav item cannot drift out of step with the route it
 * points at.
 *
 * **Filtering the nav is not authorization**, and nothing here pretends
 * otherwise: the route is still gated, and a hidden item typed into the address
 * bar still 403s. Hiding is about not showing someone a door they cannot open —
 * which is a courtesy, and also stops the nav from enumerating the platform's
 * capabilities to an agency user who should not know they exist.
 */
final class NavItem
{
    private function __construct(
        public readonly string $label,
        public readonly string $route,
        public readonly string $ability,
        public readonly string $group,
    ) {}

    public static function make(string $label, string $route, string $ability, string $group = 'Platform'): self
    {
        return new self($label, $route, $ability, $group);
    }

    public function visibleTo(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        // Explicit boolean. Gate::forUser()->allows() returns bool, but the
        // habit matters: anything truthy would open the item.
        return Gate::forUser($user)->allows($this->ability) === true;
    }
}

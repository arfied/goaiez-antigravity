<?php

declare(strict_types=1);

namespace App\Support\Account;

/**
 * One entry in the owner's navigation.
 *
 * ⚠️ **IT NAMES NO ABILITY, AND THAT IS NOT AN OMISSION.** `App\Support\Admin\
 * NavItem` carries one because the console serves several internal roles and an
 * item has to hide from the ones that cannot open it. Every screen in this nav
 * is on `auth` alone — there is no `can:` middleware on any owner route, and the
 * tenant refusal lives in code the component owns (decision 809). So there is
 * nothing here for a Gate to filter, and adding an ability that every viewer
 * passes would be a check nobody can fail: decision 597's shape, in navigation
 * rather than in an authorization screen.
 *
 * `alsoCurrentFor` exists for one case and should stay that small: a detail
 * screen has no nav entry of its own, so its parent's entry has to be the one
 * marked current. `/account/customers/{customer}` is the only such screen today.
 */
final class OwnerNavItem
{
    /**
     * The nav's two groups. `more` is `44` §2's More tab — the place its
     * follow-ups list and its due-today badge are specified to live.
     */
    public const string GROUP_PRIMARY = 'primary';

    public const string GROUP_MORE = 'more';

    /**
     * @param  array<int, string>  $alsoCurrentFor  route names or patterns whose
     *                                              screens this item stands for
     * @param  ?string  $badge  ⚠️ a KEY, never a number. This class is static
     *                          and queries nothing (it renders on the on-hold
     *                          page, for the account whose tenancy is the
     *                          problem), so an item declares *which* count
     *                          belongs beside it and `OwnerNavBadges` resolves
     *                          the number where the nav is rendered, behind a
     *                          tenant guard. Decision 1442's field, added
     *                          together with the count that fills it.
     */
    private function __construct(
        public readonly string $label,
        public readonly string $route,
        public readonly string $group,
        public readonly array $alsoCurrentFor,
        public readonly ?string $badge,
    ) {
    }

    /**
     * @param  array<int, string>  $alsoCurrentFor
     */
    public static function make(
        string $label,
        string $route,
        string $group = self::GROUP_PRIMARY,
        array $alsoCurrentFor = [],
        ?string $badge = null,
    ): self {
        return new self($label, $route, $group, $alsoCurrentFor, $badge);
    }

    /**
     * Whether the request being rendered is this item's screen, or one of the
     * detail screens it stands for.
     *
     * Returns false with no route bound — the shell is rendered by a controller
     * as well as by Livewire components, and nothing here may assume a route.
     */
    public function current(): bool
    {
        return request()->routeIs($this->route, ...$this->alsoCurrentFor);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Models\Business;
use App\Services\Tenant\TenantPause;
use App\Services\Tenant\TenantSuspension;
use App\Support\Tenancy;
use InvalidArgumentException;

/**
 * The one way the support console acts on a customer's lifecycle (`28` §9.5).
 *
 * ⚠️ **IT EXISTS BECAUSE A LINT REFUSED THE OBVIOUS IMPLEMENTATION, AND THE
 * LINT WAS RIGHT.** The first version put `Tenancy::actingAs()` in the Livewire
 * component, which is decision 624's shape exactly: `StaffTest`'s *"the support
 * console reads an account only through the directory"* names the tenancy
 * switch, and widening it for a screen would have
 * weakened a chokepoint on the widest internal gate in the application — a
 * change that reads as reasonable on its own diff and does not look like a
 * security edit. The switch moved behind a service instead, and the lint is
 * untouched. Decision 603's rule, one slice later.
 *
 * ## Why not on `AccountDirectory`
 *
 * That class's first sentence is *"the one way the support console **finds and
 * reads** a customer's account"*, and every method on it is a read that files
 * `business.viewed_by_staff`. Hanging four writes off it would make that
 * sentence false, and would put its audit rule — one entry per resolved lookup,
 * never per action — beside four methods that are not lookups. This composes it
 * instead: the re-read goes through the directory, and the writes go through
 * the two chokepoints that own the columns.
 *
 * ## What every method here does, and why it is the same shape four times
 *
 * **The row is re-read**, never held from the screen. Two agents on one account
 * is the ordinary case during an incident, and the second must not lift a
 * suspension the first applied thirty seconds ago.
 *
 * **The tenant is established by reference.** `businesses` is RLS-`FORCE`d on
 * `app.business_id`, internal staff have none, and decision 800 refused a
 * fourth time to widen that policy. `Tenancy::actingAs()` admits exactly this
 * row — the same door `AccountDirectory` opens to *read* one, used to write
 * one, which is why this slice needed no change to the boundary at all.
 *
 * **Nothing here decides who may do it.** The gates are `LifecycleAccess`',
 * asked by the component before it calls — and by the component rather than by
 * the route, because `can:` refuses during route matching and this screen sits
 * on the wider `SupportAccess` gate on purpose (630).
 */
final class AccountLifecycle
{
    public function __construct(
        private readonly AccountDirectory $accounts,
        private readonly TenantPause $pause,
        private readonly TenantSuspension $suspension,
    ) {}

    /**
     * Stop an account for cause (`28` §9.5's Suspend).
     *
     * @return bool false when the account no longer exists.
     *
     * @throws InvalidArgumentException when the reason is empty — `TenantSuspension`
     *                                  refuses it, and the caller turns that into a
     *                                  message on the field it belongs to.
     */
    public function suspend(int $businessId, string $actor, string $reason): bool
    {
        return $this->within(
            $businessId,
            fn (Business $business) => $this->suspension->suspend($business, $actor, $reason),
        );
    }

    /**
     * Take it off hold.
     *
     * ⚠️ It does **not** start the account: an owner who had paused themselves
     * before we suspended them is still paused afterwards. See
     * `TenantSuspension::lift()`.
     */
    public function lift(int $businessId, string $actor): bool
    {
        return $this->within(
            $businessId,
            fn (Business $business) => $this->suspension->lift($business, $actor),
        );
    }

    /**
     * Pause on the client's behalf (`28` §9.5, §9.3's quick-actions rail).
     *
     * ⚠️ **`TenantPause`, NOT A SECOND MECHANISM.** §9.5 words it as *"the same
     * as the owner's Pause Everything, attributed to support"*, and the whole of
     * "attributed to support" is the actor string the service already takes —
     * `Livewire\Account\Settings` reads it back to explain the pause to an owner
     * who did not apply it (825). A second pause would have been a second idea
     * of what a pause is, which is what that column's lint exists to prevent.
     *
     * The reason is required here and optional on the owner's own screen. That
     * is decision 825's split enforced on the side that has to keep it: the
     * column is nullable *because* an owner owes nobody a sentence, so nothing
     * in the database can make support give one, and the refusal has to live
     * with the caller that knows which of the two paths it is on.
     *
     * @return bool false when the account no longer exists.
     *
     * @throws InvalidArgumentException when the reason is empty
     */
    public function pause(int $businessId, string $actor, string $reason): bool
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException(
                'Support pauses an account with a reason. The owner sees it on their own screen.'
            );
        }

        return $this->within(
            $businessId,
            fn (Business $business) => $this->pause->pause($business, $actor, $reason),
        );
    }

    /**
     * Start it again for them.
     *
     * No reason asked for: it is the reversal of the line above, the owner could
     * do it themselves from `/account`, and an agent who resumed the wrong
     * account can pause it again in one click.
     */
    public function resume(int $businessId, string $actor): bool
    {
        return $this->within(
            $businessId,
            fn (Business $business) => $this->pause->resume($business, $actor),
        );
    }

    /**
     * Re-read the account and run something inside its tenancy.
     *
     * @param  callable(Business): void  $action
     * @return bool false when the account no longer exists.
     */
    private function within(int $businessId, callable $action): bool
    {
        $business = $this->accounts->business($businessId);

        if (! $business instanceof Business) {
            return false;
        }

        Tenancy::actingAs((int) $business->id, static function () use ($action, $business): void {
            $action($business);
        });

        return true;
    }
}

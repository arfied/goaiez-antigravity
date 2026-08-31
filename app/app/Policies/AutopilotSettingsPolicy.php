<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AutopilotSettings;
use App\Models\User;

/**
 * Who may change how the system behaves on a business's behalf.
 *
 * FOUND-04's authorization criterion is specific: "a `staff` user cannot change
 * autopilot settings". This is where that is true, and it is a policy rather
 * than an inline check because CLAUDE.md puts authorization in policies and
 * because there will be more than one call site.
 *
 * THE TENANT IS NOT CHECKED HERE, and that is deliberate rather than an
 * oversight. A policy answers "may this role do this?"; the tenant boundary
 * answers "is this row yours?", and the two must not be conflated. An
 * AutopilotSettings instance can only be resolved inside its own tenant — the
 * global scope hides it and row-level security blocks the query underneath — so
 * by the time a model reaches this class the boundary has already held. Adding
 * a business_id comparison here would read as the boundary and quietly become
 * the place people trust instead of the scope.
 */
final class AutopilotSettingsPolicy
{
    /**
     * Everyone signed in to a business can see how it is configured.
     *
     * Staff included: they need to know whether replies post automatically to do
     * their job, and hiding it produces guesswork rather than safety.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AutopilotSettings $settings): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role->canConfigureAutomation();
    }

    /**
     * The criterion. Staff read; they do not decide what the automation does.
     */
    public function update(User $user, AutopilotSettings $settings): bool
    {
        return $user->role->canConfigureAutomation();
    }

    /**
     * Nobody, by design.
     *
     * Every location has exactly one settings row and the system reads it on
     * every automation run. Deleting one does not "reset to defaults", it
     * removes the row those defaults live in — so the operation that looks like
     * it is wanted here is an update, and this stays closed.
     */
    public function delete(User $user, AutopilotSettings $settings): bool
    {
        return false;
    }
}

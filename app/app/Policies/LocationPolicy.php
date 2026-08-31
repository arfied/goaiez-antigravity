<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Location;
use App\Models\User;

/**
 * Who may say which website this business owns.
 *
 * THE TENANT IS NOT CHECKED HERE, deliberately — `TenantLinkRecordPolicy`'s
 * reasoning verbatim. A policy answers *"may this role do this?"*; the tenant
 * boundary answers *"is this row yours?"*, and conflating the two makes this
 * class look like the boundary and quietly become the place people trust instead
 * of the global scope and the RLS policy underneath it.
 * `LocationWebsite::confirm()` asserts the tenant itself, for that reason.
 *
 * ⚠️ **CONFIRMING THE WEBSITE IS `canConfigureAutomation()`, AND IT IS THE
 * STRONGEST THING ON THIS SCREEN.** The address a location carries is the
 * address a later slice publishes pages to, injects markup into and rolls back —
 * `29` §2 rule 32's whole subject. Decision 1083's franchisor case is what a
 * wrong answer costs: we would be writing to a website that is not our
 * customer's. A `staff` user who may see where the business lives may not move
 * where we write.
 *
 * ⚠️ **NO `delete`, BECAUSE THERE IS NO WAY TO UNSET AN ADDRESS** (5548). The
 * remedy for a wrong one is confirming the right one, which is one action rather
 * than two and leaves no state in which a tenant has actuation history against a
 * site the row no longer names.
 */
final class LocationPolicy
{
    /**
     * Everyone signed in to a business may see its locations.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Location $location): bool
    {
        return true;
    }

    public function update(User $user, Location $location): bool
    {
        return $user->role->canConfigureAutomation();
    }
}

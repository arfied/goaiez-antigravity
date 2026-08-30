<?php

declare(strict_types=1);

namespace App\Modules\X192\Actions;

use App\Modules\X192\Events\ProfileBuilt;
use App\Modules\X192\Models\DirectoryMembership;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class MembershipBuildAction
{
    /**
     * Builds and activates directory membership profile.
     * TEST ANCHOR: No membership is purchased without an approval action row.
     */
    public function buildProfile(
        int $businessId,
        int $membershipId,
        bool $isPurchased = false,
        ?int $approvalActionId = null
    ): DirectoryMembership {
        $membership = DirectoryMembership::where('business_id', $businessId)->findOrFail($membershipId);

        // TEST ANCHOR: No membership is purchased without an approval action row
        if ($isPurchased && empty($approvalActionId)) {
            throw new InvalidArgumentException('Purchase rejected: paid directory membership requires an explicit approval action row (TEST ANCHOR)');
        }

        $membership->update([
            'is_purchased' => $isPurchased,
            'approved_by_action_id' => $approvalActionId,
        ]);

        Event::dispatch(new ProfileBuilt($businessId, $membership->id, $membership->directory_name));

        return $membership;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\X165\Actions;

use App\Modules\X165\Models\Membership;

final class MembershipStatusAction
{
    public function handle(int $businessId, int $personId): ?string
    {
        $membership = Membership::where('business_id', $businessId)
            ->where('person_id', $personId)
            ->first();

        return $membership ? $membership->status : null;
    }
}

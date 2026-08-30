<?php

declare(strict_types=1);

namespace App\Modules\X165\Actions;

use App\Modules\X165\Events\MembershipRenewed;
use App\Modules\X165\Models\Membership;
use App\Modules\X165\Models\MembershipPlan;
use Illuminate\Support\Facades\Event;

final class MembershipRenewAction
{
    public function handle(int $businessId, int $membershipId): Membership
    {
        $membership = Membership::where('business_id', $businessId)->findOrFail($membershipId);
        $plan = MembershipPlan::where('business_id', $businessId)->findOrFail($membership->plan_id);

        $newRenewsAt = $membership->renews_at->copy()->addMonths($plan->billing_interval_months);

        $membership->update([
            'status' => 'active',
            'renews_at' => $newRenewsAt,
            'renewal_reminder_sent_at' => null,
        ]);

        Event::dispatch(new MembershipRenewed($businessId, $membership->id, $plan->id));

        return $membership;
    }
}

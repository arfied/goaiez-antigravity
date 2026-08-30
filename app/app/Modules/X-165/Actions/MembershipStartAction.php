<?php

declare(strict_types=1);

namespace App\Modules\X165\Actions;

use App\Modules\X165\Events\MembershipStarted;
use App\Modules\X165\Models\Membership;
use App\Modules\X165\Models\MembershipPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

final class MembershipStartAction
{
    public function handle(int $businessId, int $planId, int $personId): Membership
    {
        $plan = MembershipPlan::where('business_id', $businessId)->findOrFail($planId);
        $startsAt = Carbon::now();
        $renewsAt = $startsAt->copy()->addMonths($plan->billing_interval_months);

        $membership = Membership::create([
            'business_id' => $businessId,
            'plan_id' => $plan->id,
            'person_id' => $personId,
            'status' => 'active',
            'starts_at' => $startsAt,
            'renews_at' => $renewsAt,
        ]);

        Event::dispatch(new MembershipStarted($businessId, $membership->id, $personId, $plan->id));

        return $membership;
    }
}

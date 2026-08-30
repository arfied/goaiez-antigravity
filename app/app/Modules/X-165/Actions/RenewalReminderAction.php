<?php

declare(strict_types=1);

namespace App\Modules\X165\Actions;

use App\Modules\X165\Models\Membership;
use App\Modules\X165\Models\MembershipPlan;
use Carbon\Carbon;

final class RenewalReminderAction
{
    /**
     * A renewal reminder precedes every renewal charge by the configured days (TEST ANCHOR).
     */
    public function sendReminderIfDue(int $businessId, int $membershipId, Carbon $currentTime): array
    {
        $membership = Membership::where('business_id', $businessId)->findOrFail($membershipId);
        $plan = MembershipPlan::where('business_id', $businessId)->findOrFail($membership->plan_id);

        $reminderDueDate = $membership->renews_at->copy()->subDays($plan->renewal_reminder_days);

        if ($currentTime->greaterThanOrEqualTo($reminderDueDate) && $currentTime->lessThan($membership->renews_at)) {
            if (! $membership->renewal_reminder_sent_at) {
                $membership->update([
                    'renewal_reminder_sent_at' => $currentTime,
                ]);

                return [
                    'status' => 'reminder_sent',
                    'membership_id' => $membership->id,
                    'days_before_renewal' => $plan->renewal_reminder_days,
                    'renewal_date' => $membership->renews_at->toIso8601String(),
                ];
            }
        }

        return [
            'status' => 'not_due_or_already_sent',
            'membership_id' => $membership->id,
        ];
    }
}

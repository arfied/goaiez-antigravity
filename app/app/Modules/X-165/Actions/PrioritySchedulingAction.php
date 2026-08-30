<?php

declare(strict_types=1);

namespace App\Modules\X165\Actions;

use App\Modules\X165\Models\Membership;

final class PrioritySchedulingAction
{
    /**
     * Given conflicting booking requests for same window: member is served BEFORE non-member (TEST ANCHOR).
     */
    public function scheduleWindow(int $businessId, string $timeWindow, int $firstRequestPersonId, int $secondRequestPersonId): array
    {
        $isFirstMember = Membership::where('business_id', $businessId)
            ->where('person_id', $firstRequestPersonId)
            ->where('status', 'active')
            ->exists();

        $isSecondMember = Membership::where('business_id', $businessId)
            ->where('person_id', $secondRequestPersonId)
            ->where('status', 'active')
            ->exists();

        // If second request is a member and first is not -> member wins priority window
        $servedPersonId = ($isSecondMember && ! $isFirstMember)
            ? $secondRequestPersonId
            : $firstRequestPersonId;

        return [
            'window' => $timeWindow,
            'served_person_id' => $servedPersonId,
            'priority_applied' => ($servedPersonId === $secondRequestPersonId && $isSecondMember),
            'decision_log' => "Scheduler decision: Person #{$servedPersonId} served for window {$timeWindow} based on membership priority status",
        ];
    }
}

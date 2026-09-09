<?php

declare(strict_types=1);

namespace App\Modules\X184\Domain;

final class PlanEngine
{
    public function approveCadenceOnly(string $approvalType): bool
    {
        return $approvalType === 'cadence';
    }

    public function recommendNotAutoPost(string $actionType): bool
    {
        return $actionType === 'recommend';
    }

    public function refreshOnFatigue(bool $isFatigued): bool
    {
        return $isFatigued;
    }

    /**
     * (R245)
     */
    public function autoRefreshAllowed(bool $isFatigued, int $monthlyTraffic, int $floor): bool
    {
        if (! $isFatigued) {
            return false;
        }

        return $monthlyTraffic < $floor;
    }
}

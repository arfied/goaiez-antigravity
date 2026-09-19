<?php

declare(strict_types=1);

namespace App\Modules\X210\Domain;

use App\Modules\CBilling\Actions\TrialRateChangeAction;

final class X210Engine
{
    public function savePromotion(bool $hasCap): array
    {
        if (! $hasCap) {
            return ['status' => 'refused', 'reason' => 'promotion with no cap cannot be saved'];
        }

        return ['status' => 'saved'];
    }

    public function checkMarginGuard(array $services): array
    {
        $belowCost = [];
        foreach ($services as $service) {
            if ($service['price'] < $service['cost']) {
                $belowCost[] = $service['name'];
            }
        }

        if (! empty($belowCost)) {
            throw new \DomainException('REFUSED BELOW_COST: '.implode(', ', $belowCost));
        }

        return ['status' => 'ok', 'named_below_cost' => $belowCost];
    }

    public function cancelAction(bool $hasInterstitial): array
    {
        if ($hasInterstitial) {
            return ['status' => 'refused', 'reason' => 'one tap cancel required'];
        }

        return ['status' => 'cancelled'];
    }

    public function changeRate(int $businessId, int $newRate, bool $isNotified): array
    {
        if (! $isNotified) {
            throw new \DomainException('REFUSED: rate never changes without a NOTIFIED action');
        }

        app(TrialRateChangeAction::class)->handle($businessId, $newRate);

        return ['status' => 'changed'];
    }

    public function applyPromotion(bool $hasExistingPromo, bool $stackingAllowed): array
    {
        if ($hasExistingPromo && ! $stackingAllowed) {
            return ['status' => 'refused', 'reason' => 'no stacking by default'];
        }

        return ['status' => 'applied'];
    }

    public function validateHoldout(bool $hasHoldout): array
    {
        if (! $hasHoldout) {
            return ['status' => 'refused', 'reason' => 'incrementality holdout mandatory'];
        }

        return ['status' => 'ok'];
    }
}

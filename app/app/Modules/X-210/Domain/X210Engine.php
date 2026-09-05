<?php

declare(strict_types=1);

namespace App\Modules\X210\Domain;

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

        return ['status' => 'ok', 'named_below_cost' => $belowCost];
    }

    public function cancelAction(bool $hasInterstitial): array
    {
        if ($hasInterstitial) {
            return ['status' => 'refused', 'reason' => 'one tap cancel required'];
        }

        return ['status' => 'cancelled'];
    }

    public function changeRate(bool $isNotified): array
    {
        if (! $isNotified) {
            return ['status' => 'refused', 'reason' => 'rate never changes without a notified action'];
        }

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

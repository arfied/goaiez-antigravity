<?php

declare(strict_types=1);

namespace App\Modules\X205\Domain;

final class AffiliateEngine
{
    public function validateUtm(array $payload): array
    {
        if (isset($payload['ref']) && isset($payload['utm'])) {
            return ['status' => 'refused', 'reason' => 'ref merged with utm'];
        }

        return ['status' => 'ok'];
    }

    public function handleChargeback(bool $isPaid): array
    {
        if ($isPaid) {
            return ['status' => 'refused', 'reason' => 'a chargeback reverses a paid commission'];
        }

        return ['status' => 'ok'];
    }

    public function validateClick(bool $isSelfClick, bool $isStolenCard): array
    {
        if ($isSelfClick || $isStolenCard) {
            return ['status' => 'refused', 'reason' => 'self-clicking and stolen-card affiliates'];
        }

        return ['status' => 'ok'];
    }

    public function getTier(int $referralCount): string
    {
        if ($referralCount >= 100) {
            return 'Gold';
        }
        if ($referralCount >= 10) {
            return 'Silver';
        }

        return 'Bronze';
    }

    public function partnerLoginAccess(bool $isTenant): array
    {
        if (! $isTenant) {
            return ['status' => 'refused', 'reason' => 'partner login'];
        }

        return ['status' => 'ok'];
    }

    public function checkW9Threshold(int $payoutCents, int $thresholdCents, bool $w9Collected): array
    {
        if ($payoutCents >= $thresholdCents && ! $w9Collected) {
            return ['status' => 'frozen', 'reason' => 'W-9 threshold freezes a payout'];
        }

        return ['status' => 'ok'];
    }

    public function isCookieValid(int $cookieAgeDays): bool
    {
        return $cookieAgeDays <= 90;
    }

    public function calculateCommission(int $saleAmountCents, int $commissionRateBps): int
    {
        return (int) round(($saleAmountCents * $commissionRateBps) / 10000);
    }
}

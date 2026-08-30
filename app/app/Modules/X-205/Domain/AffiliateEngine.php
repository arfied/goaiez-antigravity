<?php

declare(strict_types=1);

namespace App\Modules\X205\Domain;

use Carbon\CarbonInterface;

final class AffiliateEngine
{
    public const COOKIE_LIFETIME_DAYS = 90; // G13-20: 90-day cookie window

    public function isWithinAttributionWindow(CarbonInterface $clickTime, CarbonInterface $saleTime): bool
    {
        return $clickTime->diffInDays($saleTime) <= self::COOKIE_LIFETIME_DAYS;
    }

    public function calculateCommission(int $saleAmountCents, int $rateBps): int
    {
        return (int) round(($saleAmountCents * $rateBps) / 10000);
    }
}

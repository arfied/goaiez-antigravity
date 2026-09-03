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

    public function parseAffiliateFromUrl(string $url): ?string
    {
        if (str_contains($url, 'utm_') && str_contains($url, 'ref=')) {
            throw new \DomainException('REFUSAL_G7_04_REF_MERGED_WITH_UTM');
        }
        return 'CODE';
    }

    public function unlockTier(int $referralCount): void
    {
        throw new \DomainException('REFUSAL_G7_41_TIERS');
    }

    public function getPartnerLoginUrl(int $affiliateId): string
    {
        throw new \DomainException('REFUSAL_G7_45_PARTNER_LOGIN');
    }
}

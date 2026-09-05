<?php

declare(strict_types=1);

namespace App\Modules\X190\Domain;

final class X190Engine
{
    public function validateReferralOffer(string $offerSource): void
    {
        if (strtolower($offerSource) !== 'tenant') {
            throw new \DomainException('REFUSES: 20% off is the TENANT\'s offer under R26, never ours [G7-25]');
        }
    }

    public function validateSocialProofToast(bool $isRealEvent): void
    {
        if (! $isRealEvent) {
            throw new \DomainException('REFUSES: the toast states a real event or does not fire [G12-33]');
        }
    }
}

<?php
declare(strict_types=1);
namespace App\Modules\X214\Domain;

final class X214Engine
{
    public function validateSurcharge(string $cardType, bool $hasDisclosed): void
    {
        if ($cardType === 'debit' || $cardType === 'unknown') {
            throw new \DomainException('REFUSES: debit is NEVER surcharged');
        }
        if (!$hasDisclosed) {
            throw new \DomainException('REFUSES: no surcharge applies without a preceding surcharge.disclosed');
        }
    }
}
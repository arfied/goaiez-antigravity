<?php

declare(strict_types=1);

namespace App\Modules\X185\Domain;

final class SequenceEngine
{
    public function validateExperiment(string $testElement): void
    {
        if (stripos($testElement, 'price') !== false) {
            throw new \DomainException('X-185 may test a label, never a price [G12-20]');
        }
    }

    public function validateOutreachPermit(string $signalSource): void
    {
        if ($signalSource === 'calendar_gap') {
            throw new \DomainException('A gap in the calendar is a signal, not a permit [G5-16]');
        }
    }

    public function validatePromotion(int $fleetSampleSize, int $tenantDataPoints): void
    {
        if ($tenantDataPoints > 0 && $fleetSampleSize === 0) {
            throw new \DomainException('Promotion requires fleet evidence, never isolated tenant data [G12-30]');
        }
        if ($fleetSampleSize < 40) {
            throw new \DomainException('A winner cannot be declared below minimum sample size [G17-01]');
        }
    }
}

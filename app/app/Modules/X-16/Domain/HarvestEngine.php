<?php

declare(strict_types=1);

namespace App\Modules\X16\Domain;

use DomainException;

final class HarvestEngine
{
    public function assertDistressSignal(string $signalType): void
    {
        if ($signalType === 'distress') {
            throw new DomainException('a distress signal, never a send permit (P-068)');
        }
    }
}

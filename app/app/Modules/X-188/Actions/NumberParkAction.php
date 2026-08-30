<?php

declare(strict_types=1);

namespace App\Modules\X188\Actions;

use App\Modules\X188\Domain\NumberPoolManager;

final class NumberParkAction
{
    public function __construct(private readonly NumberPoolManager $manager) {}

    public function handle(int $businessId, bool $isPayingTenant, int $usageCount): array
    {
        return $this->manager->handleCancellation($businessId, $isPayingTenant, $usageCount);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\X188\Actions;

use App\Modules\X188\Domain\NumberPoolManager;

final class NumberAssignAction
{
    public function __construct(private readonly NumberPoolManager $manager) {}

    public function handle(int $businessId, string $areaCode = '512'): array
    {
        return $this->manager->assignLiveNumber($businessId, $areaCode);
    }
}

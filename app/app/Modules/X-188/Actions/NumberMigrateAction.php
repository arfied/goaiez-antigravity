<?php

declare(strict_types=1);

namespace App\Modules\X188\Actions;

use App\Modules\X188\Domain\NumberPoolManager;

final class NumberMigrateAction
{
    public function __construct(private readonly NumberPoolManager $manager) {}

    public function handle(int $businessId, string $brandName, string $tcrId): array
    {
        return $this->manager->migrateBrand($businessId, $brandName, $tcrId);
    }
}

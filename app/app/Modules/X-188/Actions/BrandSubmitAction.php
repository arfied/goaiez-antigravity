<?php

declare(strict_types=1);

namespace App\Modules\X188\Actions;

use App\Modules\X188\Domain\NumberPoolManager;
use App\Modules\X188\Models\BrandRegistration;

final class BrandSubmitAction
{
    public function __construct(private readonly NumberPoolManager $manager) {}

    public function handle(int $businessId, string $brandName): BrandRegistration
    {
        return $this->manager->submitBrand($businessId, $brandName);
    }
}

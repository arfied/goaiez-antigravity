<?php

declare(strict_types=1);

namespace App\Modules\CTelephony\Actions;

use App\Modules\CTelephony\Domain\CarrierRouter;

final class CarrierCallAction
{
    public function __construct(private readonly CarrierRouter $router) {}

    public function handle(int $businessId, string $fromPhone, string $toPhone, string $shakenStirGrade = 'A'): array
    {
        return $this->router->screenInbound($businessId, $fromPhone, $shakenStirGrade);
    }
}

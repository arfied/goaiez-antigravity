<?php

declare(strict_types=1);

namespace App\Modules\CTelephony\Actions;

use App\Modules\CTelephony\Domain\CarrierRouter;

final class CarrierSendAction
{
    public function __construct(private readonly CarrierRouter $router) {}

    public function handle(
        int $businessId,
        string $threadKey,
        string $toPhone,
        string $body,
        bool $isRcs = false,
        ?string $preferredCarrier = null
    ): array {
        return $this->router->send($businessId, $threadKey, $toPhone, $body, $isRcs, $preferredCarrier);
    }
}

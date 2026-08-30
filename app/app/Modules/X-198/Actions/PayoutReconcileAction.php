<?php

declare(strict_types=1);

namespace App\Modules\X198\Actions;

use App\Modules\X198\Domain\GatewayEngine;

final class PayoutReconcileAction
{
    public function __construct(private readonly GatewayEngine $engine) {}

    public function handle(int $businessId, int $payoutId, int $expectedCents, int $actualCents): array
    {
        return $this->engine->reconcilePayout($businessId, $payoutId, $expectedCents, $actualCents);
    }
}

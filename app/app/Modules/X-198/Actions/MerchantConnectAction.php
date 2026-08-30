<?php

declare(strict_types=1);

namespace App\Modules\X198\Actions;

use App\Modules\X198\Domain\GatewayEngine;
use App\Modules\X198\Models\MerchantConnection;

final class MerchantConnectAction
{
    public function __construct(private readonly GatewayEngine $engine) {}

    public function handle(int $businessId, string $gatewayName, string $merchantAccountId): MerchantConnection
    {
        return $this->engine->connect($businessId, $gatewayName, $merchantAccountId);
    }
}

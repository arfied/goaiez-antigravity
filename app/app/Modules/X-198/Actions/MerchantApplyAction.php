<?php

declare(strict_types=1);

namespace App\Modules\X198\Actions;

use App\Modules\X198\Domain\GatewayEngine;

class MerchantApplyAction
{
    public function __construct(private GatewayEngine $engine) {}

    public function execute(int $businessId): array
    {
        return $this->engine->applyForSubMerchant($businessId);
    }
}

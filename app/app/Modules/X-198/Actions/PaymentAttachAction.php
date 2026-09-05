<?php

declare(strict_types=1);

namespace App\Modules\X198\Actions;

use App\Modules\X198\Domain\GatewayEngine;
use App\Modules\X198\Models\Payment;

class PaymentAttachAction
{
    public function __construct(
        private readonly GatewayEngine $engine
    ) {}

    public function handle(int $businessId, int $paymentId, int $connectionId): Payment
    {
        return $this->engine->attachPayment($businessId, $paymentId, $connectionId);
    }
}

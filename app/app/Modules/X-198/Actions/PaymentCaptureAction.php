<?php

declare(strict_types=1);

namespace App\Modules\X198\Actions;

use App\Modules\X198\Domain\GatewayEngine;
use App\Modules\X198\Models\Payment;

final class PaymentCaptureAction
{
    public function __construct(private readonly GatewayEngine $engine) {}

    public function handle(
        int $businessId,
        int $amountCents,
        ?string $paymentToken,
        string $idempotencyKey
    ): Payment|array {
        return $this->engine->capture($businessId, $amountCents, $paymentToken, $idempotencyKey);
    }
}

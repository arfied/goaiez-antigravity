<?php

declare(strict_types=1);

namespace App\Modules\X170\Actions;

use App\Modules\X170\Domain\CommissionEngine;

final class CommissionReleaseAction
{
    public function __construct(private readonly CommissionEngine $engine = new CommissionEngine) {}

    public function handle(int $businessId, int $commissionId, ?string $paymentCapturedId): array
    {
        return $this->engine->release($businessId, $commissionId, $paymentCapturedId);
    }
}

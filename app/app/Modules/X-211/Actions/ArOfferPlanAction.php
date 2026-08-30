<?php

declare(strict_types=1);

namespace App\Modules\X211\Actions;

use App\Modules\X211\Domain\ArEngine;
use App\Modules\X211\Models\PaymentPlan;

final class ArOfferPlanAction
{
    public function __construct(private readonly ArEngine $engine) {}

    public function handle(int $businessId, int $invoiceId, int $installmentsCount = 3, string $frequency = 'monthly'): PaymentPlan
    {
        return $this->engine->offerPlan($businessId, $invoiceId, $installmentsCount, $frequency);
    }
}

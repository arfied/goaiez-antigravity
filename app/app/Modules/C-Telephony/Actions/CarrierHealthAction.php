<?php

declare(strict_types=1);

namespace App\Modules\CTelephony\Actions;

use App\Modules\CTelephony\Models\CarrierHealth;

final class CarrierHealthAction
{
    public function updateStatus(int $businessId, string $carrierName, string $status, int $latencyMs = 150): CarrierHealth
    {
        return CarrierHealth::updateOrCreate(
            ['business_id' => $businessId, 'carrier_name' => $carrierName],
            ['status' => $status, 'latency_ms' => $latencyMs]
        );
    }
}

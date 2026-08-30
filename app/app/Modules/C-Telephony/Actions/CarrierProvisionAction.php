<?php

declare(strict_types=1);

namespace App\Modules\CTelephony\Actions;

use App\Modules\CTelephony\Models\CarrierRoster;

final class CarrierProvisionAction
{
    public function handle(
        int $businessId,
        string $carrierName,
        string $adapterClass,
        bool $supportsRcs = false,
        bool $supportsVoice = true
    ): CarrierRoster {
        return CarrierRoster::updateOrCreate(
            ['business_id' => $businessId, 'carrier_name' => $carrierName],
            [
                'adapter_class' => $adapterClass,
                'is_active' => true,
                'supports_rcs' => $supportsRcs,
                'supports_voice' => $supportsVoice,
            ]
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\X163\Actions;

use App\Modules\X163\Models\PriceBookItem;

final class PriceRangeAction
{
    public function handle(int $businessId, string $serviceName): array
    {
        $item = PriceBookItem::where('business_id', $businessId)->where('service_name', $serviceName)->first();

        return [
            'service_name' => $serviceName,
            'min_cents' => $item?->price_min_cents ?? 5000,
            'max_cents' => $item?->price_max_cents ?? 15000,
        ];
    }
}

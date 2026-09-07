<?php

declare(strict_types=1);

namespace App\Modules\X163\Actions;

use App\Modules\X163\Models\PriceBookItem;

final class PriceRangeAction
{
    public function handle(int $businessId, string $serviceName): array
    {
        $item = PriceBookItem::where('business_id', $businessId)
            ->where('service_name', $serviceName)
            ->where('is_confirmed', true)
            ->where('is_sample', false)
            ->first();

        if (! $item) {
            return [
                'refusal_code' => 'NO_FACT',
                'reason' => 'No price quote available for the given intent',
            ];
        }

        return [
            'service_name' => $serviceName,
            'min_cents' => $item->price_min_cents,
            'max_cents' => $item->price_max_cents,
        ];
    }
}

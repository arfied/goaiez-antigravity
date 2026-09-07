<?php

declare(strict_types=1);

namespace App\Modules\X163\Actions;

use App\Modules\X163\Models\PriceBookItem;

final class PriceRangeAction
{
    public function handle(int $businessId, string $serviceName): array
    {
        $serviceKey = PriceBookItem::serviceKey($serviceName);

        $item = PriceBookItem::where('business_id', $businessId)
            ->where('service_key', $serviceKey)
            ->where('is_confirmed', true)
            ->where('is_sample', false)
            ->first();

        if ($item) {
            return [
                'service_name' => $serviceName,
                'min_cents' => $item->price_min_cents,
                'max_cents' => $item->price_max_cents,
            ];
        }

        $item = PriceBookItem::where('business_id', $businessId)
            ->where('service_key', $serviceKey)
            ->first();

        if ($item) {
            if ($item->is_sample === true) {
                return [
                    'refusal_code' => 'SAMPLE_STATE_REFUSED',
                    'reason' => 'Sample prices must NEVER be returned to any customer channel',
                ];
            }

            return [
                'refusal_code' => 'UNCONFIRMED',
                'reason' => 'Unconfirmed prices must NEVER be returned to any customer channel',
            ];
        }

        return [
            'refusal_code' => 'NO_FACT',
            'reason' => 'No price quote available for the given intent',
        ];
    }
}

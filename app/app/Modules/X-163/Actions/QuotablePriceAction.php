<?php

declare(strict_types=1);

namespace App\Modules\X163\Actions;

use App\Modules\X163\Models\PriceBookItem;

/**
 * (R245) a price another module may copy: a confirmed, non-sample, confirmable pricebook row
 */
final class QuotablePriceAction
{
    public function options(int $businessId): array
    {
        return PriceBookItem::where('business_id', $businessId)
            ->where('is_confirmed', true)
            ->where('is_sample', false)
            ->orderBy('service_name')
            ->get()
            ->filter(fn (PriceBookItem $item) => PriceConfirmAction::confirmable($item->price_cents))
            ->map(fn (PriceBookItem $item) => [
                'id' => $item->id,
                'service_name' => $item->service_name,
                'price_cents' => $item->price_cents,
            ])
            ->values()
            ->all();
    }

    public function resolve(int $businessId, int $itemId): array
    {
        $item = PriceBookItem::where('business_id', $businessId)->find($itemId);

        if ($item === null) {
            return ['refusal_code' => 'NO_FACT'];
        }

        if ($item->is_sample) {
            return ['refusal_code' => 'SAMPLE_STATE_REFUSED'];
        }

        if (! $item->is_confirmed) {
            return ['refusal_code' => 'UNCONFIRMED'];
        }

        if (! PriceConfirmAction::confirmable($item->price_cents)) {
            return ['refusal_code' => 'FILL_ME'];
        }

        return [
            'amount' => $item->price_cents,
            'service_name' => $item->service_name,
        ];
    }
}

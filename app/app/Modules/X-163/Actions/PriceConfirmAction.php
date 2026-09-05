<?php

declare(strict_types=1);

namespace App\Modules\X163\Actions;

use App\Modules\X163\Events\PricebookUpdated;
use App\Modules\X163\Events\PriceConfirmed;
use App\Modules\X163\Models\PriceBookItem;
use Illuminate\Support\Facades\Event;

final class PriceConfirmAction
{
    public function handle(int $businessId, int $itemId): array
    {
        $item = PriceBookItem::where('business_id', $businessId)->findOrFail($itemId);

        if ($item->price_cents <= 0) {
            return [
                'item_id' => $item->id,
                'is_confirmed' => false,
                'refusal_code' => 'FILL_ME',
            ];
        }

        $item->update([
            'is_confirmed' => true,
            'is_sample' => false,
            'confirmed_at' => $item->confirmed_at ?? now(),
        ]);

        Event::dispatch(new PriceConfirmed($businessId, $item->id));
        Event::dispatch(new PricebookUpdated($businessId, $item->id, $item->service_name, $item->price_cents));

        return [
            'item_id' => $item->id,
            'is_confirmed' => true,
            'is_sample' => false,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\X163\Listeners;

use App\Modules\X163\Events\PriceRefusalFlagged;
use App\Modules\X163\Models\PriceBookItem;

/**
 * (R245) A NO_FACT price refusal on any channel records the gap
 * row, and a refusal caused by rows that already exist and disagree records nothing.
 * The service name is normalised for whitespace and a blank one records nothing.
 */
final class RecordPriceGapFromRefusal
{
    public function handle(PriceRefusalFlagged $event): void
    {
        if ($event->refusalReason !== 'NO_FACT') {
            return;
        }

        $serviceName = substr(preg_replace('/\s+/', ' ', trim($event->serviceName)), 0, 255);

        if ($serviceName === '') {
            return;
        }

        $serviceKey = PriceBookItem::serviceKey($serviceName);

        if (PriceBookItem::where('business_id', $event->businessId)
            ->where('service_key', $serviceKey)
            ->where('is_confirmed', true)
            ->exists()) {
            return;
        }

        $item = PriceBookItem::firstOrCreate(
            [
                'business_id' => $event->businessId,
                'service_key' => $serviceKey,
                'location_book_id' => null,
            ],
            [
                'service_name' => $serviceName,
                'price_cents' => 0,
                'is_confirmed' => false,
                'is_sample' => false,
                'refusal_count' => 0,
                'location_book_id' => null,
            ]
        );

        $item->increment('refusal_count', 1, ['refusal_flagged_at' => now()]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\X163\Listeners;

use App\Modules\CAgent\Events\AgentRefused;
use App\Modules\X163\Models\PriceBookItem;
use Illuminate\Support\Str;

/**
 * (R245) A NO_FACT agent refusal about pricebook creates a business-wide,
 * price_cents=0, is_confirmed=false row so the owner sees the gap in the daily digest.
 * The service name is normalised for whitespace and a blank one records nothing.
 */
final class RecordPriceGap
{
    public function handle(AgentRefused $event): void
    {
        if ($event->refusalCode !== 'NO_FACT') {
            return;
        }

        if (! Str::contains(strtolower($event->reason), 'pricebook')) {
            return;
        }

        $serviceName = substr(preg_replace('/\s+/', ' ', trim($event->userInput)), 0, 255);

        if ($serviceName === '') {
            return;
        }

        $item = PriceBookItem::firstOrCreate(
            [
                'business_id' => $event->businessId,
                'service_key' => PriceBookItem::serviceKey($serviceName),
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

<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X163\Models\CalloutFee;
use App\Modules\X163\Models\LocationBook;
use App\Modules\X163\Models\PriceBookItem;

class X163Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-163';
    }

    public function fill(Business $business): int
    {
        if (PriceBookItem::where('business_id', $business->id)->where('service_name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }
        $loc = LocationBook::create(['business_id' => $business->id, 'location_name' => self::MARKER.'Location', 'version' => 1]);
        CalloutFee::create(['business_id' => $business->id, 'explanation_text' => self::MARKER.'Fee', 'callout_fee_cents' => 15000, 'location_book_id' => $loc->id]);
        for ($i = 0; $i < 5; $i++) {
            PriceBookItem::create(['business_id' => $business->id, 'service_name' => "Item $i", 'price_cents' => 10000, 'location_book_id' => $loc->id, 'tax_rate_pct' => 0, 'is_sample' => true]);
        }

        return 7;
    }

    public function purge(Business $business): int
    {
        $count = PriceBookItem::where('business_id', $business->id)->where('is_sample', true)->delete();
        $count += CalloutFee::where('business_id', $business->id)->where('explanation_text', 'like', self::MARKER.'%')->delete();
        $count += LocationBook::where('business_id', $business->id)->where('location_name', 'like', self::MARKER.'%')->delete();

        return $count;
    }
}

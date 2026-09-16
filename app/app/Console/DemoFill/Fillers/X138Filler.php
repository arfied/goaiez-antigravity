<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use Illuminate\Support\Facades\DB;

class X138Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-138';
    }

    public function fill(Business $business): int
    {
        if (DB::table('attribution_queries')->where('business_id', $business->id)->where('attribution_status', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        DB::table('attribution_queries')->insert([
            ['business_id' => $business->id, 'touches' => json_encode([['source' => 'organic_search']]), 'job_value' => 50000, 'job_id' => '1001', 'attribution_status' => self::MARKER.'single'],
            ['business_id' => $business->id, 'touches' => json_encode([['source' => 'direct']]), 'job_value' => null, 'job_id' => '1002', 'attribution_status' => self::MARKER.'ambiguous'],
        ]);

        DB::table('roi_snapshots')->insert([
            ['business_id' => $business->id, 'campaign_name' => self::MARKER.'Autumn Promo', 'ad_spend_cents' => 10000, 'closed_revenue_cents' => 80000],
        ]);

        return 3;
    }

    public function purge(Business $business): int
    {
        $count = DB::table('attribution_queries')->where('business_id', $business->id)->where('attribution_status', 'like', self::MARKER.'%')->delete();
        $count += DB::table('roi_snapshots')->where('business_id', $business->id)->where('campaign_name', 'like', self::MARKER.'%')->delete();

        return $count;
    }
}

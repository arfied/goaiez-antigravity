<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X166\Models\JobCost;

class X166Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-166';
    }

    public function fill(Business $business): int
    {
        if (JobCost::where('business_id', $business->id)->where('is_sample', true)->exists()) {
            return 0;
        }
        for ($i = 0; $i < 3; $i++) {
            JobCost::create(['business_id' => $business->id, 'job_id' => 100 + $i, 'price_book_version' => 'v1', 'tech_id' => $business->owner_user_id, 'service_type' => 'Svc1', 'source' => 'Src1', 'revenue_cents' => 5000, 'total_cost_cents' => 1000, 'is_sample' => true]);
            JobCost::create(['business_id' => $business->id, 'job_id' => 200 + $i, 'price_book_version' => 'v1', 'tech_id' => $business->owner_user_id, 'service_type' => 'Svc2', 'source' => 'Src2', 'revenue_cents' => 8000, 'total_cost_cents' => 2000, 'is_sample' => true]);
        }

        return 6;
    }

    public function purge(Business $business): int
    {
        return JobCost::where('business_id', $business->id)->where('is_sample', true)->delete();
    }
}

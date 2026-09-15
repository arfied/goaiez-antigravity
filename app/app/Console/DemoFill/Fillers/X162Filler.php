<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X162\Models\DispatchAssignment;

class X162Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-162';
    }

    public function fill(Business $business): int
    {
        if (DispatchAssignment::where('business_id', $business->id)->where('is_sample', true)->exists()) {
            return 0;
        }

        DispatchAssignment::create([
            'business_id' => $business->id,
            'job_id' => 100,
            'tech_id' => $business->owner_user_id,
            'status' => 'dispatched',
            'is_sample' => true,
        ]);

        DispatchAssignment::create([
            'business_id' => $business->id,
            'job_id' => 101,
            'tech_id' => $business->owner_user_id,
            'status' => 'dispatched',
            'is_sample' => true,
        ]);

        return 2;
    }

    public function purge(Business $business): int
    {
        return DispatchAssignment::where('business_id', $business->id)
            ->where('is_sample', true)
            ->delete();
    }
}

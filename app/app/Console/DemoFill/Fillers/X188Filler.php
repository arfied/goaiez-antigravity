<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X188\Models\NumberAssignment;
use App\Modules\X188\Models\NumberPark;
use App\Modules\X188\Models\NumberPool;

class X188Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-188';
    }

    public function fill(Business $business): int
    {
        if (NumberPool::where('business_id', $business->id)->where('phone_number', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }
        $pool1 = NumberPool::create(['business_id' => $business->id, 'phone_number' => self::MARKER.'+15125551881', 'area_code' => '512', 'complaint_count' => 0]);
        $pool2 = NumberPool::create(['business_id' => $business->id, 'phone_number' => self::MARKER.'+15125551882', 'area_code' => '512', 'complaint_count' => 3]);
        NumberPark::create(['business_id' => $business->id, 'phone_number_id' => $pool1->id]);
        NumberAssignment::create(['business_id' => $business->id, 'phone_number_id' => $pool2->id, 'status' => 'active']);

        return 4;
    }

    public function purge(Business $business): int
    {
        $poolIds = NumberPool::where('business_id', $business->id)->where('phone_number', 'like', self::MARKER.'%')->pluck('id');
        $count = NumberPark::whereIn('phone_number_id', $poolIds)->delete();
        $count += NumberAssignment::whereIn('phone_number_id', $poolIds)->delete();
        $count += NumberPool::whereIn('id', $poolIds)->delete();

        return $count;
    }
}

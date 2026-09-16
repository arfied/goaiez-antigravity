<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X108\Models\Appointment;
use App\Modules\X108\Models\Resource;
use App\Modules\X108\Models\Waitlist;

class X108Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-108';
    }

    public function fill(Business $business): int
    {
        if (Resource::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $resource = Resource::create([
            'business_id' => $business->id,
            'name' => self::MARKER.'Demo Resource',
            'calendar_type' => 'user',
            'is_active' => true,
        ]);

        Appointment::create([
            'business_id' => $business->id,
            'resource_id' => $resource->id,
            'service_name' => self::MARKER.'Demo Service',
            'start_time' => now(),
            'end_time' => now()->addHour(),
            'status' => 'booked',
            'is_member' => false,
        ]);

        Waitlist::create([
            'business_id' => $business->id,
            'customer_name' => self::MARKER.'Demo Customer',
            'customer_phone' => '+15125550199',
            'service_name' => self::MARKER.'Demo Service',
            'preferred_date' => now()->addDay(),
            'is_member' => false,
            'status' => 'waiting',
        ]);

        return 3;
    }

    public function purge(Business $business): int
    {
        $waitCount = Waitlist::where('business_id', $business->id)
            ->where('customer_name', 'like', self::MARKER.'%')
            ->delete();

        $aptCount = Appointment::where('business_id', $business->id)
            ->where('service_name', 'like', self::MARKER.'%')
            ->delete();

        $resCount = Resource::where('business_id', $business->id)
            ->where('name', 'like', self::MARKER.'%')
            ->delete();

        return $waitCount + $aptCount + $resCount;
    }
}

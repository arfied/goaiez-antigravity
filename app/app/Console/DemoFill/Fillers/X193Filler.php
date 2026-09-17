<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X193\Models\NotificationClass;

class X193Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-193';
    }

    public function fill(Business $business): int
    {
        if (NotificationClass::where('business_id', $business->id)->where('caller_type', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        NotificationClass::create([
            'business_id' => $business->id,
            'caller_type' => self::MARKER.'missed_call',
            'classification' => 'transactional',
            'respects_quiet_hours' => false,
        ]);

        NotificationClass::create([
            'business_id' => $business->id,
            'caller_type' => self::MARKER.'dunning',
            'classification' => 'account',
            'respects_quiet_hours' => false,
        ]);

        NotificationClass::create([
            'business_id' => $business->id,
            'caller_type' => self::MARKER.'marketing',
            'classification' => 'marketing',
            'respects_quiet_hours' => true,
            'quiet_hours_start' => 21,
            'quiet_hours_end' => 8,
        ]);

        return 3;
    }

    public function purge(Business $business): int
    {
        $count = NotificationClass::where('business_id', $business->id)->where('caller_type', 'like', self::MARKER.'%')->delete();

        return $count;
    }
}

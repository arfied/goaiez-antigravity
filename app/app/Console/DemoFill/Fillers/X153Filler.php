<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X153\Models\Alert;
use App\Modules\X153\Models\ReplyCode;

class X153Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-153';
    }

    public function fill(Business $business): int
    {
        if (Alert::where('business_id', $business->id)->where('title', 'like', self::MARKER . '%')->exists()) {
            return 0;
        }

        $a1 = Alert::create([
            'business_id' => $business->id,
            'alert_class' => 'urgent',
            'title' => self::MARKER . 'Angry customer on the line',
            'body' => self::MARKER . 'Angry customer on the line',
            'status' => 'pending',
            'claim_expires_at' => now()->addMinutes(30),
        ]);

        $a2 = Alert::create([
            'business_id' => $business->id,
            'alert_class' => 'missed_call',
            'title' => self::MARKER . 'Missed call from a new lead',
            'body' => self::MARKER . 'Missed call from a new lead',
            'status' => 'claimed',
            'claim_expires_at' => now()->subHour(),
        ]);

        ReplyCode::create([
            'business_id' => $business->id,
            'alert_id' => $a1->id,
            'code' => 'A7K2Q',
            'is_live' => true,
        ]);

        return 2;
    }

    public function purge(Business $business): int
    {
        return Alert::where('business_id', $business->id)
            ->where('title', 'like', self::MARKER . '%')
            ->delete();
    }
}

<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\CSms\Models\SmsComposition;
use App\Modules\X204\Models\Suppression;

class CSmsFiller implements DemoFiller
{
    public function module(): string
    {
        return 'C-Sms';
    }

    public function fill(Business $business): int
    {
        if (SmsComposition::where('business_id', $business->id)->where('body', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        SmsComposition::create([
            'business_id' => $business->id,
            'recipient_phone' => '+15125550142',
            'message_class' => 'transactional',
            'body' => self::MARKER.'Your technician is on the way, arriving in about 20 minutes.',
            'segments_count' => 1,
            'encoding' => 'gsm7',
            'status' => 'sent',
        ]);

        SmsComposition::create([
            'business_id' => $business->id,
            'recipient_phone' => '+15125550177',
            'message_class' => 'transactional',
            'body' => self::MARKER.'Thanks for calling — reply YES and we will book the water heater visit.',
            'segments_count' => 1,
            'encoding' => 'gsm7',
            'status' => 'sent',
        ]);

        SmsComposition::create([
            'business_id' => $business->id,
            'recipient_phone' => '+15125550188',
            'message_class' => 'marketing',
            'body' => self::MARKER.'Spring tune-up special: book any HVAC service this month and we will include a free filter change, a thermostat check and a written efficiency report for your records.',
            'segments_count' => 2,
            'encoding' => 'gsm7',
            'status' => 'halted',
        ]);

        Suppression::create([
            'business_id' => $business->id,
            'recipient_phone' => '+15125550199',
            'channel' => 'sms',
            'reason' => self::MARKER.'replied STOP',
            'suppressed_at' => now(),
        ]);

        return 4;
    }

    public function purge(Business $business): int
    {
        $compositions = SmsComposition::where('business_id', $business->id)
            ->where('body', 'like', self::MARKER.'%')
            ->delete();

        $suppressions = Suppression::where('business_id', $business->id)
            ->where('reason', 'like', self::MARKER.'%')
            ->delete();

        return $compositions + $suppressions;
    }
}

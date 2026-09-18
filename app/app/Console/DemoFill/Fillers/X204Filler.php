<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X204\Models\ComplianceRegister;
use App\Modules\X204\Models\SendPermit;

class X204Filler implements DemoFiller
{
    public const MARKER = 'demo·';

    public function module(): string
    {
        return 'X-204';
    }

    public function fill(Business $business): int
    {
        $added = 0;

        if (! SendPermit::where('business_id', $business->id)->where('recipient_phone', 'like', self::MARKER.'%')->exists()) {
            SendPermit::create([
                'business_id' => $business->id,
                'recipient_phone' => self::MARKER.'+15550100001',
                'channel' => 'sms',
                'permit_status' => 'refused',
                'refusal_reason' => 'archived',
                'expires_at' => now()->addDays(3),
            ]);
            $added += 1;
        }

        if (! ComplianceRegister::where('business_id', $business->id)->where('register_name', 'like', self::MARKER.'%')->exists()) {
            ComplianceRegister::create([
                'business_id' => $business->id,
                'register_name' => self::MARKER.'TCPA Safe Harbor',
                'status' => 'compliant',
                'slot_states' => ['slot1' => 'valid'],
            ]);
            $added += 1;
        }

        return $added;
    }

    public function purge(Business $business): int
    {
        $removed = 0;
        $removed += SendPermit::where('business_id', $business->id)->where('recipient_phone', 'like', self::MARKER.'%')->delete();
        $removed += ComplianceRegister::where('business_id', $business->id)->where('register_name', 'like', self::MARKER.'%')->delete();

        return $removed;
    }
}

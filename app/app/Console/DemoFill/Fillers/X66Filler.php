<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X66\Models\CallSession;
use App\Modules\X66\Models\CallTurn;
use App\Modules\X66\Models\Voicemail;

class X66Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-66';
    }

    public function fill(Business $business): int
    {
        if (CallSession::where('business_id', $business->id)->where('call_sid', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $session1 = CallSession::create([
            'business_id' => $business->id,
            'call_sid' => self::MARKER.'CA1',
            'from_phone' => '+15125550142',
            'to_phone' => '+15125550100',
            'status' => 'completed',
            'latency_ms' => 320,
        ]);

        CallTurn::create([
            'business_id' => $business->id,
            'session_id' => $session1->id,
            'turn_index' => 1,
            'speaker' => 'caller',
            'transcript' => self::MARKER.'Hi, do you service water heaters?',
        ]);

        CallTurn::create([
            'business_id' => $business->id,
            'session_id' => $session1->id,
            'turn_index' => 2,
            'speaker' => 'agent',
            'transcript' => self::MARKER.'Yes — I can book a visit for tomorrow morning.',
        ]);

        $session2 = CallSession::create([
            'business_id' => $business->id,
            'call_sid' => self::MARKER.'CA2',
            'from_phone' => '+15125550177',
            'to_phone' => '+15125550100',
            'status' => 'missed',
            'latency_ms' => 0,
        ]);

        Voicemail::create([
            'business_id' => $business->id,
            'call_session_id' => $session2->id,
            'transcription' => self::MARKER.'Calling about a leaking water heater, please call back.',
            'duration_seconds' => 18,
        ]);

        return 2;
    }

    public function purge(Business $business): int
    {
        $sessionIds = CallSession::where('business_id', $business->id)
            ->where('call_sid', 'like', self::MARKER.'%')
            ->pluck('id');

        CallTurn::whereIn('session_id', $sessionIds)->delete();
        Voicemail::whereIn('call_session_id', $sessionIds)->delete();
        $sessions = CallSession::whereIn('id', $sessionIds)->delete();

        return $sessions;
    }
}

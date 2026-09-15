<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X137\Models\CallToken;

class X137Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-137';
    }

    public function fill(Business $business): int
    {
        if (CallToken::where('business_id', $business->id)->where('whisper_text', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }
        for ($i = 0; $i < 2; $i++) {
            CallToken::create(['business_id' => $business->id, 'visitor_session_token' => "Tok$i", 'allocated_number' => '+15125554471', 'expires_at' => now()->addDays(1), 'whisper_text' => self::MARKER.'Call from website direct']);
        }

        return 2;
    }

    public function purge(Business $business): int
    {
        return CallToken::where('business_id', $business->id)->where('whisper_text', 'like', self::MARKER.'%')->delete();
    }
}

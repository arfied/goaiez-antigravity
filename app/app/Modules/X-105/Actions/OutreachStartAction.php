<?php

declare(strict_types=1);

namespace App\Modules\X105\Actions;

use App\Modules\X105\Events\OutreachSent;
use App\Modules\X105\Models\LadderStep;
use App\Modules\X105\Models\OutreachLadder;
use Illuminate\Support\Facades\Event;

final class OutreachStartAction
{
    /**
     * Starts an outreach ladder.
     * Research fires ONLY on distress (rating < 3.5 or distress signal); a healthy business NEVER triggers research (TEST ANCHOR & G1-26).
     * No SendPermit writes (P-068, G1-25).
     */
    public function startLadder(
        int $businessId,
        int $personId,
        float $businessRating = 4.8,
        bool $explicitDistress = false
    ): OutreachLadder {
        $isDistressed = ($explicitDistress || $businessRating < 3.5);

        $ladder = OutreachLadder::create([
            'business_id' => $businessId,
            'person_id' => $personId,
            'status' => 'active',
            'distress_signal_detected' => $isDistressed,
            'research_triggered' => $isDistressed, // TEST ANCHOR: healthy prospect never triggers research
            'exclusive_sms_mode' => false,
        ]);

        // Create 4 rungs
        $rungs = [
            1 => 'email',
            2 => 'voice_drop',
            3 => 'sms',
            4 => 'direct_mail',
        ];

        foreach ($rungs as $num => $channel) {
            LadderStep::create([
                'business_id' => $businessId,
                'ladder_id' => $ladder->id,
                'rung_number' => $num,
                'channel' => $channel,
                'status' => ($num === 1) ? 'sent' : 'pending',
                'content_preview' => "Outreach rung {$num} via {$channel}",
            ]);
        }

        Event::dispatch(new OutreachSent($businessId, $ladder->id, 1, 'email'));

        return $ladder;
    }
}

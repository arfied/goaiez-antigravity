<?php

declare(strict_types=1);

namespace App\Modules\X200\Actions;

use App\Modules\X200\Models\DialerSeat;
use App\Modules\X200\Models\QaScorecard;

final class QaScoreAction
{
    /**
     * Scores a call session.
     * AI seat scored identically to human (G2-09, G5-40).
     * Scorecard is positive only (G2-35, G16-15, T677).
     */
    public function scoreCall(
        int $businessId,
        int $seatId,
        int $callId,
        int $score = 95,
        ?string $coachingNote = null
    ): QaScorecard {
        $seat = DialerSeat::where('business_id', $businessId)->findOrFail($seatId);

        return QaScorecard::create([
            'business_id' => $businessId,
            'seat_id' => $seat->id,
            'call_id' => $callId,
            'qa_rating' => max(0, min(100, $score)),
            'coaching_note' => $coachingNote ?? 'positive execution on objection handling',
            'is_positive_only' => true,
        ]);
    }
}

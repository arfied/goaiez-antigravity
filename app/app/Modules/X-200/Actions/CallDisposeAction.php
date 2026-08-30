<?php

declare(strict_types=1);

namespace App\Modules\X200\Actions;

use App\Modules\X200\Models\CallDisposition;
use App\Modules\X200\Models\DialerSeat;

final class CallDisposeAction
{
    /**
     * Disposes call.
     * §18C.4: uncertain treat as human, never drop a voicemail on a live person (G18-01).
     */
    public function disposeCall(
        int $businessId,
        int $campaignId,
        int $seatId,
        string $phone,
        string $disposition,
        bool $isUncertainAmd = false
    ): CallDisposition {
        $finalDisp = $disposition;
        if ($isUncertainAmd && $disposition === 'voicemail') {
            // Treat uncertain as human (G18-01)
            $finalDisp = 'answered';
        }

        $disp = CallDisposition::create([
            'business_id' => $businessId,
            'campaign_id' => $campaignId,
            'seat_id' => $seatId,
            'phone' => $phone,
            'disposition' => $finalDisp,
            'is_uncertain_human' => $isUncertainAmd,
        ]);

        $seat = DialerSeat::where('business_id', $businessId)->find($seatId);
        if ($seat) {
            $seat->update(['state' => 'idle']);
        }

        return $disp;
    }
}

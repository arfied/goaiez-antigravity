<?php

declare(strict_types=1);

namespace App\Modules\X200\Actions;

use App\Modules\X200\Models\DialerSeat;

final class SeatLogoutAction
{
    public function logout(int $businessId, int $seatId): void
    {
        $seat = DialerSeat::where('business_id', $businessId)->findOrFail($seatId);
        $seat->update([
            'is_logged_in' => false,
            'state' => 'idle',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\X200\Actions;

use App\Modules\X200\Models\DialerSeat;

final class SeatLoginAction
{
    public function login(int $businessId, string $agentName, bool $isAi = false): DialerSeat
    {
        return DialerSeat::create([
            'business_id' => $businessId,
            'agent_name' => $agentName,
            'is_ai_agent' => $isAi,
            'is_logged_in' => true,
            'state' => 'idle',
        ]);
    }
}

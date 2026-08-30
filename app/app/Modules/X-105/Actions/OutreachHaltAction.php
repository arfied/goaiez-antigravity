<?php

declare(strict_types=1);

namespace App\Modules\X105\Actions;

use App\Modules\X105\Models\LadderStep;
use App\Modules\X105\Models\OutreachLadder;

final class OutreachHaltAction
{
    public function halt(int $businessId, int $ladderId): OutreachLadder
    {
        $ladder = OutreachLadder::where('business_id', $businessId)->findOrFail($ladderId);
        $ladder->update(['status' => 'halted']);

        LadderStep::where('business_id', $businessId)
            ->where('ladder_id', $ladderId)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        return $ladder;
    }
}

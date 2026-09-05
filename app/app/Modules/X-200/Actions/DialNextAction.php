<?php

declare(strict_types=1);

namespace App\Modules\X200\Actions;

use App\Modules\X200\Events\CallRequested;
use App\Modules\X200\Models\CallCampaign;
use App\Modules\X200\Models\DialerSeat;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class DialNextAction
{
    public function dialNext(int $businessId, int $campaignId, int $seatId, string $phone): void
    {
        $campaign = CallCampaign::where('business_id', $businessId)->findOrFail($campaignId);
        $seat = DialerSeat::where('business_id', $businessId)->findOrFail($seatId);

        if (! $campaign->is_running) {
            throw new InvalidArgumentException('Dial rejected: campaign is paused');
        }

        $seat->update(['state' => 'dialing']);

        Event::dispatch(new CallRequested($businessId, $campaign->id, $seat->id, $phone));
    }
}

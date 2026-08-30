<?php

declare(strict_types=1);

namespace App\Modules\X105\Actions;

use App\Modules\X105\Events\DemoRequested;
use App\Modules\X105\Models\OutreachLadder;
use Illuminate\Support\Facades\Event;

final class DemoRequestAction
{
    public function request(int $businessId, int $ladderId, string $preferredTime): OutreachLadder
    {
        $ladder = OutreachLadder::where('business_id', $businessId)->findOrFail($ladderId);
        $ladder->update(['status' => 'demo_requested']);

        Event::dispatch(new DemoRequested($businessId, (int) $ladder->person_id, $preferredTime));

        return $ladder;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\X136\Actions;

use App\Modules\X136\Events\ProspectDecayed;
use App\Modules\X136\Models\SignalScore;
use Illuminate\Support\Facades\Event;

final class SignalListAction
{
    public function listActive(int $businessId): array
    {
        return SignalScore::where('business_id', $businessId)->get()->toArray();
    }

    public function markDecayed(int $businessId, string $prospectIdentifier, int $daysInactive): void
    {
        SignalScore::where('business_id', $businessId)
            ->where('prospect_identifier', $prospectIdentifier)
            ->update(['cooling_status' => 'decayed']);

        Event::dispatch(new ProspectDecayed($businessId, $prospectIdentifier, $daysInactive));
    }
}

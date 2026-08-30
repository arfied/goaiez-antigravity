<?php

declare(strict_types=1);

namespace App\Modules\X209\Actions;

use App\Modules\X209\Events\FixerPromoted;
use App\Modules\X209\Models\FixerLadder;
use Illuminate\Support\Facades\Event;

final class FixerApproveAction
{
    public function approve(int $businessId, string $actionName): FixerLadder
    {
        $ladder = FixerLadder::firstOrCreate(
            ['business_id' => $businessId, 'action_name' => $actionName],
            ['current_level' => 3, 'success_count' => 0]
        );

        $ladder->increment('success_count');
        $newLevel = min(5, $ladder->current_level + 1);
        $ladder->update(['current_level' => $newLevel]);

        Event::dispatch(new FixerPromoted($businessId, $actionName, $newLevel));

        return $ladder;
    }
}

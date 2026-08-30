<?php

declare(strict_types=1);

namespace App\Modules\X184\Actions;

use App\Modules\X184\Events\ItemScheduled;
use App\Modules\X184\Models\PlanItem;
use Illuminate\Support\Facades\Event;

final class PlanScheduleAction
{
    public function scheduleItem(int $businessId, int $itemId): PlanItem
    {
        $item = PlanItem::where('business_id', $businessId)->findOrFail($itemId);

        $item->update([
            'is_scheduled' => true,
        ]);

        Event::dispatch(new ItemScheduled($businessId, $item->id, $item->scheduled_date->toDateString()));

        return $item;
    }
}

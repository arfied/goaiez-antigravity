<?php

declare(strict_types=1);

namespace App\Modules\X202\Actions;

use App\Modules\X202\Events\ApprovalEscalated;
use App\Modules\X202\Models\ApprovalItem;
use Illuminate\Support\Facades\Event;

final class ApprovalEscalateAction
{
    public function handle(int $businessId, int $approvalItemId, string $reason): array
    {
        $item = ApprovalItem::where('business_id', $businessId)->findOrFail($approvalItemId);
        $item->update(['status' => 'escalated']);

        Event::dispatch(new ApprovalEscalated($businessId, $item->id, $reason));

        return [
            'approval_item_id' => $item->id,
            'status' => 'escalated',
            'reason' => $reason,
        ];
    }
}

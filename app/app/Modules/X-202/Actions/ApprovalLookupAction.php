<?php

declare(strict_types=1);

namespace App\Modules\X202\Actions;

use App\Modules\X202\Models\ApprovalItem;

final class ApprovalLookupAction
{
    /**
     * The id of an APPROVED item for this type and subject, or null.
     * Callers outside X-202 use this rather than reading ApprovalItem directly (boundary).
     */
    public function approvedIdFor(int $businessId, string $itemType, string $subject): ?int
    {
        $id = ApprovalItem::where('business_id', $businessId)
            ->where('item_type', $itemType)
            ->where('subject', $subject)
            ->where('status', 'approved')
            ->orderByDesc('id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }
}

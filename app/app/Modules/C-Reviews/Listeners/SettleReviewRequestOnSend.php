<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Listeners;

use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\CSms\Events\SendSettled;

final class SettleReviewRequestOnSend
{
    public function handle(SendSettled $event): void
    {
        if ($event->source !== 'review_request') {
            return;
        }
        ReviewRequest::where('business_id', $event->businessId)
            ->where('id', $event->compositionId)
            ->where('status', 'queued')
            ->update([
                'status' => $event->status,
                'settled_reason' => $event->reason,
                'settled_at' => now(),
            ]);
    }
}

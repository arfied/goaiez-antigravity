<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Actions;

use App\Modules\CReviews\Models\ReviewRequest;

final class QaTicketAction
{
    public function handle(int $businessId, int $reviewRequestId): array
    {
        $req = ReviewRequest::where('business_id', $businessId)->findOrFail($reviewRequestId);
        $req->update(['status' => 'triaged_internal']);

        return [
            'review_request_id' => $req->id,
            'ticket_status' => 'open_sla_24h',
            'rating' => $req->rating,
        ];
    }
}

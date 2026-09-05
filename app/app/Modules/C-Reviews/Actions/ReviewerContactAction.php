<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Actions;

use App\Models\ConsentRecord;
use App\Modules\CReviews\Models\ReviewRequest;

final class ReviewerContactAction
{
    public function handle(int $businessId, int $reviewRequestId): array
    {
        $req = ReviewRequest::where('business_id', $businessId)->findOrFail($reviewRequestId);

        if ($req->customer_id === null) {
            return [
                'status' => 'refused',
                'refusal_code' => 'REVIEWER_NAME_IS_NOT_CONSENT',
            ];
        }

        $consent = ConsentRecord::query()->where('customer_id', $req->customer_id)
            ->orderByRaw('created_at DESC NULLS LAST')
            ->latest('id')
            ->first();

        if ($consent === null) {
            return [
                'status' => 'refused',
                'refusal_code' => 'NO_CONSENT_RECORD',
            ];
        }

        return [
            'status' => 'sent',
            'review_request_id' => $req->id,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Actions;

use App\Modules\CReviews\Models\ReviewRemovalRequest;
use Illuminate\Support\Carbon;

final class PrepareRemovalRequestAction
{
    public function execute(int $businessId, int $reviewRequestId, string $tosGround, string $preparedBody, ?string $googleReviewId): ReviewRemovalRequest
    {
        return ReviewRemovalRequest::create([
            'business_id' => $businessId,
            'review_request_id' => $reviewRequestId,
            'google_review_id' => $googleReviewId,
            'tos_ground' => $tosGround,
            'prepared_body' => $preparedBody,
            'prepared_at' => Carbon::now(),
            'status' => 'prepared',
            'confirmed_by_user_id' => null,
            'confirmed_at' => null,
        ]);
    }
}

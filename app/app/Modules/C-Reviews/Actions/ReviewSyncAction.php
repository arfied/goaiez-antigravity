<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Actions;

use App\Modules\CReviews\Events\ReviewReceived;
use App\Modules\CReviews\Models\ReviewRequest;
use Illuminate\Support\Facades\Event;

final class ReviewSyncAction
{
    public function handle(
        int $businessId,
        string $platform,
        int $rating,
        string $reviewText,
        bool $gbpSuspended = false
    ): ReviewRequest {
        $req = ReviewRequest::create([
            'business_id' => $businessId,
            'platform' => $platform,
            'rating' => $rating,
            'review_text' => $reviewText,
            'status' => 'received',
            'gbp_suspended' => $gbpSuspended,
        ]);

        Event::dispatch(new ReviewReceived(
            businessId: $businessId,
            reviewRequestId: $req->id,
            rating: $rating,
            platform: $platform
        ));

        return $req;
    }
}

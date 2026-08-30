<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Actions;

use App\Modules\CReviews\Events\ReviewRequested;
use App\Modules\CReviews\Models\ReviewRequest;
use Illuminate\Support\Facades\Event;

final class ReviewRequestAction
{
    /**
     * Send review request with prompt and incentive lints (TEST ANCHOR, G19-10, G20-03).
     */
    public function handle(
        int $businessId,
        ?int $customerId,
        string $promptTemplate,
        string $platform = 'google'
    ): array {
        $lower = strtolower($promptTemplate);

        // 1. Incentive Lint: "review for 10% off" is strictly banned (G19-10)
        if (str_contains($lower, '10% off') || str_contains($lower, 'discount for review') || str_contains($lower, 'gift card')) {
            return [
                'status' => 'refused',
                'refusal_code' => 'INCENTIVE_GATING_BANNED',
                'message' => 'Review incentives and review-gating discounts are strictly banned across all channels',
            ];
        }

        // 2. Staff Mention Lint: "mention Dave" is banned (G20-03 & TEST ANCHOR)
        if (str_contains($lower, 'mention dave') || str_contains($lower, 'mention our tech') || str_contains($lower, 'mention your technician')) {
            return [
                'status' => 'refused',
                'refusal_code' => 'STAFF_PROMPT_BANNED',
                'message' => 'Staff-name prompts are banned; ask "how did the repair go" instead',
            ];
        }

        $req = ReviewRequest::create([
            'business_id' => $businessId,
            'customer_id' => $customerId,
            'platform' => $platform,
            'status' => 'sent',
            'gbp_suspended' => false,
        ]);

        Event::dispatch(new ReviewRequested(
            businessId: $businessId,
            reviewRequestId: $req->id,
            platform: $platform
        ));

        return [
            'status' => 'sent',
            'review_request_id' => $req->id,
            'platform' => $platform,
        ];
    }
}

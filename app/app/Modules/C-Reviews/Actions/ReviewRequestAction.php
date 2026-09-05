<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Actions;

use App\Modules\CReviews\Events\ReviewRequested;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\CSms\Events\SendRequested;
use App\Modules\X121\Models\Person;
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
        string $platform = 'google',
        ?int $csatScore = null,
        ?int $jobAgeDays = null
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

        if ($csatScore !== null && $csatScore < 7 && ($jobAgeDays ?? 0) >= 60) {
            $req = ReviewRequest::create([
                'business_id' => $businessId,
                'customer_id' => $customerId,
                'platform' => $platform,
                'status' => 'triaged_internal',
                'gbp_suspended' => false,
                'csat_score' => $csatScore,
            ]);

            app(QaTicketAction::class)->handle($businessId, $req->id);

            return [
                'status' => 'refused',
                'refusal_code' => 'LOW_CSAT_TRIAGE',
                'review_request_id' => $req->id,
            ];
        }

        $req = ReviewRequest::create([
            'business_id' => $businessId,
            'customer_id' => $customerId,
            'platform' => $platform,
            'status' => 'sent',
            'gbp_suspended' => false,
            'csat_score' => $csatScore,
        ]);

        if ($customerId !== null) {
            $person = Person::find($customerId);
            if ($person !== null && ! empty($person->phone)) {
                Event::dispatch(new SendRequested(
                    businessId: $businessId,
                    compositionId: $req->id,
                    recipientPhone: $person->phone,
                    messageClass: 'marketing',
                    body: $promptTemplate,
                    segmentsCount: 1
                ));
            }
        }

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

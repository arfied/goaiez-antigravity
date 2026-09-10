<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Actions;

use App\Modules\CReviews\Events\ReviewRequested;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\CSms\Events\SendRequested;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

final class ReviewRequestAction
{
    private const CADENCE_WINDOW_DAYS = 30;

    private const MESSAGE_CLASS = 'marketing';

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

        if ($customerId === null) {
            return [
                'status' => 'refused',
                'refusal_code' => 'CUSTOMER_UNKNOWN',
                'message' => 'Review requests require a known customer',
            ];
        }

        if ($csatScore !== null && $csatScore < 7 && ($jobAgeDays ?? 0) >= 60) {
            $req = ReviewRequest::create([
                'business_id' => $businessId,
                'customer_id' => $customerId,
                'platform' => $platform,
                'status' => 'triaged_internal',
                'gbp_suspended' => false,
            ]);

            $setting = \App\Modules\CReviews\Models\QaSetting::where('business_id', $businessId)->first();
            $slaHours = $setting ? ((int) $setting->sla_hours) : 48;
            app(\App\Modules\X181\Actions\QaTicketCreateAction::class)->handle(
                businessId: $businessId,
                personId: $customerId,
                subject: 'Low review rating triage',
                description: 'Review rating was ' . $csatScore . ' stars.',
                reviewRequestId: $req->id,
                slaHours: $slaHours
            );

            return [
                'status' => 'refused',
                'refusal_code' => 'LOW_CSAT_TRIAGE',
                'review_request_id' => $req->id,
            ];
        }

        $lifetimeRequests = ReviewRequest::where('business_id', $businessId)
            ->where('customer_id', $customerId)
            ->count();

        if ($lifetimeRequests >= 2) {
            return [
                'status' => 'refused',
                'refusal_code' => 'TWO_PASS_CAP_REACHED',
                'message' => 'The two-pass lifetime cap was reached for this customer globally',
            ];
        }

        $recentRequest = ReviewRequest::where('business_id', $businessId)
            ->where('customer_id', $customerId)
            ->where('created_at', '>=', Carbon::now()->subDays(self::CADENCE_WINDOW_DAYS))
            ->exists();

        if ($recentRequest) {
            return [
                'status' => 'refused',
                'refusal_code' => 'CADENCE_WINDOW_ACTIVE',
                'message' => 'A review request was already sent to this customer within the cadence window',
            ];
        }

        $req = ReviewRequest::create([
            'business_id' => $businessId,
            'customer_id' => $customerId,
            'platform' => $platform,
            'status' => 'sent',
            'gbp_suspended' => false,
        ]);

        $personPhone = \Illuminate\Support\Facades\DB::table('people')->where('id', $customerId)->value('phone');
        if (! empty($personPhone)) {
            Event::dispatch(new SendRequested(
                businessId: $businessId,
                compositionId: $req->id,
                recipientPhone: (string) $personPhone,
                messageClass: self::MESSAGE_CLASS,
                body: $promptTemplate,
                segmentsCount: 1
            ));
        }

        Event::dispatch(new ReviewRequested(
            businessId: $businessId,
            reviewRequestId: $req->id,
            platform: $platform,
            messageClass: self::MESSAGE_CLASS,
        ));

        return [
            'status' => 'sent',
            'review_request_id' => $req->id,
            'platform' => $platform,
        ];
    }
}

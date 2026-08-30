<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Actions;

use App\Modules\CReviews\Events\FirstWin;
use App\Modules\CReviews\Events\ReplyPublished;
use App\Modules\CReviews\Models\ReviewReply;
use App\Modules\CReviews\Models\ReviewRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class ReviewReplyAction
{
    /**
     * Reply to a review with 1-3 star triage and GBP suspension protection (TEST ANCHOR, G20-01, G20-14).
     */
    public function handle(
        int $businessId,
        int $reviewRequestId,
        string $replyText,
        bool $isSarcasticOrAmbiguous = false
    ): array {
        return DB::transaction(function () use ($businessId, $reviewRequestId, $replyText, $isSarcasticOrAmbiguous) {
            $req = ReviewRequest::where('business_id', $businessId)->findOrFail($reviewRequestId);

            // 1. GBP Suspended Check (TEST ANCHOR: never published while gbp.suspended is true)
            if ($req->gbp_suspended) {
                return [
                    'status' => 'refused',
                    'refusal_code' => 'GBP_SUSPENDED',
                    'message' => 'Review reply cannot be published because Google Business Profile is suspended',
                ];
            }

            // 2. 1-3 Star Review Rule (TEST ANCHOR & G20-01: 1-3 star review has NO public reply row ever)
            if ($req->rating !== null && $req->rating <= 3) {
                // Route to internal ticket triage without creating public reply row
                $req->update(['status' => 'triaged_internal']);

                return [
                    'status' => 'triaged_internal',
                    'rating' => $req->rating,
                    'is_public' => false,
                    'message' => 'Low rating (1-3 stars) triaged internally with SLA; no public reply row created',
                ];
            }

            // 3. Ambiguity/Sarcasm check (G20-14: drafted to inbox, never published directly)
            if ($isSarcasticOrAmbiguous) {
                $reply = ReviewReply::create([
                    'business_id' => $businessId,
                    'review_request_id' => $req->id,
                    'reply_text' => $replyText,
                    'is_public' => false,
                    'status' => 'draft',
                ]);

                return [
                    'status' => 'draft',
                    'reply_id' => $reply->id,
                    'is_public' => false,
                    'message' => 'Ambiguous or sarcastic review drafted to inbox for human review',
                ];
            }

            // 4. 4-5 Star Review -> Public Auto-Reply
            $reply = ReviewReply::create([
                'business_id' => $businessId,
                'review_request_id' => $req->id,
                'reply_text' => $replyText,
                'is_public' => true,
                'published_at' => now(),
                'status' => 'published',
            ]);

            $req->update(['status' => 'published_public']);

            Event::dispatch(new ReplyPublished(
                businessId: $businessId,
                replyId: $reply->id,
                platform: $req->platform
            ));

            Event::dispatch(new FirstWin(
                businessId: $businessId,
                reviewSnippet: $req->review_text ?? '5-star positive review'
            ));

            return [
                'status' => 'published',
                'reply_id' => $reply->id,
                'is_public' => true,
                'reply_text' => $replyText,
            ];
        });
    }
}

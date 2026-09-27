<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Enums\ReviewSource;
use App\Enums\ReviewStatus;
use App\Models\Location;
use App\Models\Review;
use App\Services\Zernio\FacebookReview;
use App\Services\Zernio\FacebookReviewPage;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class FacebookReviewIngest
{
    /**
     * @return array{inserted: int, updated: int, unrated: int}
     */
    public function upsertPage(Location $location, FacebookReviewPage $page): array
    {
        if ((int) $location->business_id !== Tenancy::idOrFail()) {
            throw new InvalidArgumentException(
                'Facebook review ingest refuses a location outside the current tenant.',
            );
        }

        $inserted = 0;
        $updated = 0;
        $unrated = 0;

        foreach ($page->reviews as $review) {
            if ($review->rating === null) {
                $unrated++;

                continue;
            }

            $outcome = DB::transaction(function () use ($location, $review): string {
                $existing = Review::query()
                    ->where('location_id', $location->id)
                    ->where('source', ReviewSource::Facebook)
                    ->where('provider_review_id', $review->externalId)
                    ->first();

                $payload = $this->reducedPayload($review);

                if ($existing === null) {
                    Review::query()->create([
                        'location_id' => $location->id,
                        'business_id' => $location->business_id,
                        'source' => ReviewSource::Facebook,
                        'provider_review_id' => $review->externalId,
                        'is_platform' => true,
                        'ingest_method' => 'api',
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'reviewer_name' => $review->authorName,
                        'review_create_time' => $review->createdAt,
                        'review_update_time' => $review->updatedAt,
                        'recommendation' => $review->recommendation,
                        'status' => ReviewStatus::Approved,
                        'display_on_website' => false, // TODO(Q-045): Owner decision pending
                        'approved_at' => now(),
                        'approved_by' => 'system:facebook_sync',
                        'raw_payload' => $payload,
                    ]);

                    return 'inserted';
                }

                $existing->rating = $review->rating;
                $existing->comment = $review->comment;
                $existing->reviewer_name = $review->authorName;
                $existing->review_create_time = $review->createdAt ?? $existing->review_create_time;
                $existing->review_update_time = $review->updatedAt;
                $existing->recommendation = $review->recommendation;
                $existing->status = ReviewStatus::Approved;
                $existing->display_on_website = false; // TODO(Q-045): Owner decision pending
                $existing->is_platform = true;
                $existing->ingest_method = 'api';
                $existing->raw_payload = $payload;
                $existing->save();

                return 'updated';
            });

            if ($outcome === 'inserted') {
                $inserted++;
            } else {
                $updated++;
            }
        }

        return compact('inserted', 'updated', 'unrated');
    }

    /**
     * @return array<string, mixed>
     */
    private function reducedPayload(FacebookReview $review): array
    {
        return [
            'external_id' => $review->externalId,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'author_name' => $review->authorName,
            'created_at' => $review->createdAt?->toIso8601String(),
            'updated_at' => $review->updatedAt?->toIso8601String(),
            'has_owner_reply' => $review->hasOwnerReply,
            'recommendation' => $review->recommendation,
        ];
    }

    public function recordOwnerReply(Review $review, string $text, ?string $replyRef): void
    {
        if ((int) $review->business_id !== Tenancy::idOrFail()) {
            throw new InvalidArgumentException('Review belongs to another tenant.');
        }

        if ($review->source !== ReviewSource::Facebook) {
            throw new InvalidArgumentException('Review source must be Facebook.');
        }

        $review->owner_reply_text = $text;
        $review->owner_reply_ref = $replyRef;
        $review->owner_replied_at = now()->toDateTimeString();
        $review->save();
    }
}

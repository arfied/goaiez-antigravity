<?php

declare(strict_types=1);

namespace App\Services\Gbp;

use App\Enums\AutopilotActionType;
use App\Enums\ReviewSource;
use App\Enums\ReviewStatus;
use App\Events\ReviewIngested;
use App\Jobs\Reviews\GenerateReplyJob;
use App\Models\AutopilotSettings;
use App\Models\Location;
use App\Models\Review;
use App\Modules\X207\Jobs\SendPushToUserJob;
use App\Services\ActivityService;
use App\Services\Reviews\ReviewReplies;
use App\Support\Tenancy;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only writer of Google-sourced `reviews` rows.
 *
 * Decisions 409 / 386 / 1356: ingest sets `status = approved` and
 * `display_on_website = true` mechanically. Routing Google through
 * ReviewDisplay is the wrong repair — that service refuses Google in both
 * directions (408), and this class never calls it.
 *
 * ⚠️ Reply generation is slice J. On *insert* only, when auto_reply is on and
 * Google says nobody has replied, this class dispatches GenerateReplyJob after
 * the transaction commits — never on update, or a re-sync would spam drafts.
 *
 * ⛔ **AND ON *UPDATE* IT IS ALSO THE ONLY PLACE IN THIS APPLICATION THAT EVER
 * HOLDS A FRESH ANSWER TO "DID OUR REPLY LAND?"** (7120–7139). Every provider
 * report about a review arrives here — the fifteen-minute `gbp:sync` walk and
 * the `review.new` / `review.updated` webhook both funnel into
 * {@see self::upsertOne()} — so this is where an unanswered reply attempt can
 * be settled against something Google actually said. **The freshness is
 * structural rather than argued**: the observation is the payload in hand, not
 * a column somebody wrote earlier, which is what 6831 could not say about a
 * reconciliation reading `reviews.raw_payload` after the fact.
 *
 * ⚠️ **IT REACHES `ReviewReplies` AND NEVER A REPLY ROW.** `replies` has one
 * writer (1680) and `Architecture\ReviewsTest` fails the build on any other
 * file in `app/` touching the table, so the query, the lock and the clear all
 * live there and this class hands over a review and a report.
 */
final class GoogleReviewIngest
{
    public function __construct(
        private readonly ActivityService $activity,
        private readonly ReviewReplies $replies,
    ) {}

    /**
     * Upsert one page of Google reviews for a location.
     *
     * @param  list<GbpReview>  $reviews
     * @param  ?CarbonInterface  $observedAt  When the provider was asked. Null
     *                                        means unknown — see
     *                                        {@see self::upsertOne()}.
     * @return array{inserted: int, updated: int, skipped: int}
     */
    public function upsertMany(Location $location, array $reviews, ?CarbonInterface $observedAt = null): array
    {
        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($reviews as $review) {
            $outcome = $this->upsertOne($location, $review, $observedAt);

            match ($outcome) {
                'inserted' => $inserted++,
                'updated' => $updated++,
                'skipped' => $skipped++,
            };
        }

        return compact('inserted', 'updated', 'skipped');
    }

    /**
     * ⚠️ **`$observedAt` DEFAULTS TO NULL AND NULL MEANS "DO NOT RECONCILE"**
     * (7125). It is the moment the provider was **asked**, which is `now()` for
     * a live `gbp:sync` read and Zernio's own event `timestamp` for a webhook —
     * their retry schedule runs to roughly 51 hours and *"retries and
     * redeliveries keep the original value"*, so a delivery can describe a
     * review as it stood two days ago. A caller that does not know, or cannot
     * read the timestamp it was sent, passes null and settles nothing; the
     * dangerous default would be `now()`, so it is not the default.
     *
     * @return 'inserted'|'updated'|'skipped'
     */
    public function upsertOne(Location $location, GbpReview $review, ?CarbonInterface $observedAt = null): string
    {
        if ($review->rating === null) {
            // reviews.rating is NOT NULL with CHECK 1–5. A null here is Google's
            // STAR_RATING_UNSPECIFIED through Zernio — inventing a star would be
            // fabricating a rating; skipping keeps the row out rather than wrong.
            return 'skipped';
        }

        if ((int) $location->business_id !== Tenancy::idOrFail()) {
            throw new InvalidArgumentException(
                'Google review ingest refuses a location outside the current tenant.',
            );
        }

        /*
         * The review id to draft a reply for, decided inside the transaction and
         * dispatched after it (1745).
         *
         * ⚠️ THE DISPATCH USED TO SIT INSIDE THE CLOSURE WITH `afterCommit()`,
         * AND THAT IS NOT OUTSIDE THE TRANSACTION. Laravel runs `afterCommit`
         * callbacks from within `Connection::commit()`, so on `QUEUE_CONNECTION
         * = sync` the whole of `GenerateReplyJob` executes there — and a
         * transient AI failure (`unreachable`, `http_429`, `http_5xx`) makes it
         * throw. The exception then propagates out of `DB::transaction()` and
         * out of the webhook controller: the review row committed, the reply row
         * committed, the feed item was filed, and Google still received a 500
         * and retried a delivery that had already succeeded. Production runs the
         * database queue, so this was local and CI only — but the property the
         * retry work was written to establish was only half established.
         */
        $draftReplyFor = null;

        /*
         * The row a provider report may settle an unanswered reply attempt
         * against, captured inside the transaction and acted on after it — the
         * same shape as `$draftReplyFor` above and for the same reason (1745).
         * Only the update branch can carry one: a review this sync has just
         * inserted has never had a reply of ours.
         */
        $reconcile = null;
        $insertedRowId = null;

        $outcome = DB::transaction(function () use ($location, $review, &$draftReplyFor, &$reconcile, &$insertedRowId): string {
            $existing = Review::query()
                ->where('location_id', $location->id)
                ->where('google_review_id', $review->externalId)
                ->first();

            $payload = $this->reducedPayload($review);

            if ($existing === null) {
                // create() rather than property assignment: Review::$location_id
                // is int<0, max> from the schema, while Model::$id is bare int —
                // the same shape FeedbackSubmission uses for first-party rows.
                $row = Review::query()->create([
                    'location_id' => $location->id,
                    'google_review_id' => $review->externalId,
                    'source' => ReviewSource::Google,
                    'is_platform' => true,
                    'ingest_method' => 'api',
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'reviewer_name' => $review->authorName,
                    'review_create_time' => $review->createdAt,
                    'review_update_time' => $review->updatedAt,
                    'status' => ReviewStatus::Approved,
                    'display_on_website' => true,
                    'approved_at' => now(),
                    'approved_by' => 'system:google_sync',
                    'raw_payload' => $payload,
                ]);

                $this->activity->record(
                    AutopilotActionType::GoogleReviewReceived,
                    $location->id,
                    [
                        'review_id' => $row->id,
                        'rating' => $review->rating,
                    ],
                );

                if (! $review->hasOwnerReply) {
                    $settings = AutopilotSettings::query()
                        ->where('location_id', $location->id)
                        ->first();

                    if ($settings === null || $settings->auto_reply) {
                        $draftReplyFor = (int) $row->id;
                    }
                }

                $insertedRowId = (int) $row->id;

                return 'inserted';
            }

            $existing->rating = $review->rating;
            $existing->comment = $review->comment;
            $existing->reviewer_name = $review->authorName;
            $existing->review_create_time = $review->createdAt ?? $existing->review_create_time;
            $existing->review_update_time = $review->updatedAt;
            $existing->status = ReviewStatus::Approved;
            $existing->display_on_website = true;
            $existing->is_platform = true;
            $existing->ingest_method = 'api';
            $existing->raw_payload = $payload;
            // Never touch moderation / routing columns — CHECKs refuse it, and
            // rule 1 forbids it.
            $existing->save();

            $reconcile = $existing;

            return 'updated';
        });

        if ($draftReplyFor !== null) {
            GenerateReplyJob::dispatch(
                (int) $location->business_id,
                (int) $location->id,
                $draftReplyFor,
            );
        }

        if ($reconcile instanceof Review && $observedAt !== null) {
            // ⚠️ **OUTSIDE THE TRANSACTION, LIKE THE DISPATCH ABOVE** (1745).
            // It takes a row lock of its own and files an activity item; nesting
            // that inside the review upsert would hold both rows for the length
            // of the slower write and would put a feed item inside a commit that
            // can still roll back.
            $this->replies->reconcileUnconfirmedPublication(
                $reconcile,
                $review->ownerReplyReported,
                $observedAt,
            );
        }

        if ($outcome === 'inserted') {
            $ownerUserId = Tenancy::actingAs(
                (int) $location->business_id,
                fn () => DB::table('businesses')->where('id', $location->business_id)->value('owner_user_id')
            );

            if ($ownerUserId !== null) {
                SendPushToUserJob::dispatch(
                    (int) $location->business_id,
                    (int) $ownerUserId,
                    'new_review',
                    '/account'
                );
            }

            if ($insertedRowId !== null) {
                event(new ReviewIngested(
                    businessId: (int) $location->business_id,
                    reviewId: $insertedRowId,
                    platform: 'google',
                    rating: $review->rating,
                    locationId: (int) $location->id,
                ));
            }
        }

        return $outcome;
    }

    /**
     * @return array<string, mixed>
     */
    private function reducedPayload(GbpReview $review): array
    {
        return [
            'external_id' => $review->externalId,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'author_name' => $review->authorName,
            'created_at' => $review->createdAt?->toIso8601String(),
            'updated_at' => $review->updatedAt?->toIso8601String(),
            'has_owner_reply' => $review->hasOwnerReply,
        ];
    }
}

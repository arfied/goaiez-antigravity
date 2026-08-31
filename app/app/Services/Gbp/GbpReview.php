<?php

declare(strict_types=1);

namespace App\Services\Gbp;

use Illuminate\Support\Carbon;

/**
 * One Google review, reduced to what ingest can actually use.
 *
 * A closed shape rather than the raw response array, for {@see
 * \App\Services\Places\PlaceSummary}'s reason turned around: there the cost of
 * an unused field was money, here it is **stored personal data**. Zernio's
 * review objects carry reviewer photo URLs and a per-page aggregate summary;
 * neither is needed to record that a review exists, and CLAUDE.md's tie-breaker
 * for an ambiguous call is "(2) less stored PII". A raw array invites a later
 * reader to persist an avatar URL because it was there.
 *
 * ## Two fields that are not what they look like
 *
 * ⚠️ **`rating` is nullable, and a null is not a missing value.** Zernio
 * normalises Google's `STAR_RATING` enum to an integer, and its documented range
 * is **0–5** rather than 1–5. Zero is Google's `STAR_RATING_UNSPECIFIED`
 * surfacing through the translation — a review that exists with no star value.
 * It is mapped to null here rather than passed through, because a `0` reaching
 * an average silently drags it down, and a `0` reaching `reviews.rating` is a
 * CHECK violation at best and a fabricated one-star at worst.
 *
 * ⚠️ **`hasOwnerReply` is derived from the presence of a reply object**, which
 * is the only thing the review payload actually asserts. It does not mean *we*
 * replied, and it must never be reported as a reply we sent — the same
 * distinction decision 113 draws for destination clicks, where the platform
 * gives us no completion callback either.
 *
 * ⛔ **AND THERE IS A THIRD FIELD BECAUSE `false` CAME FROM TWO PLACES AND MEANT
 * TWO THINGS** (7120–7125). `hasOwnerReply` is a `bool`, so an unreadable
 * payload — a renamed key, a `"false"` string, a `reply` object whose `id` moved
 * — produces exactly the same `false` as a review Google genuinely has no owner
 * reply on. That was harmless while the only consumer was
 * `GoogleReviewIngest`'s *"should we draft one?"* question, where the two
 * readings agree: draft a reply either way. It stops being harmless the moment
 * a `false` is treated as **evidence that a reply we sent is not on the
 * listing**, which is what `ReviewReplies::reconcileUnconfirmedPublication()`
 * does. {@see self::$ownerReplyReported} is the same fact with the third
 * answer kept: `true`, `false`, or **`null` for "the payload did not say"**.
 *
 * ⚠️ **THE TWO ARE DELIBERATELY NOT COLLAPSED INTO ONE NULLABLE FIELD.** Every
 * existing reader wants the boolean and wants an unreadable payload to read as
 * *"nobody has replied"* — that is the conservative answer for a drafting
 * decision and the reckless one for a reconciliation, and a single field cannot
 * be both. Widening `hasOwnerReply` to `?bool` would push that choice onto four
 * call sites that currently do not have to think about it.
 */
final readonly class GbpReview
{
    public function __construct(
        /**
         * The provider's own id for this review, stable across reads.
         *
         * The idempotency key for ingest: a review is re-read on every sync and
         * must not insert twice. Opaque — never parsed, never displayed.
         */
        public string $externalId,
        /**
         * 1–5, or null when the provider reported no star value.
         */
        public ?int $rating,
        /**
         * The review body as the customer wrote it, or null when they left
         * stars and no words — which is common and is not an error.
         *
         * ⚠️ Customer free text, from a member of the public, containing
         * anything they chose to type. It never reaches an AI provider:
         * `AnalyzeReviewJob` refuses a Google-sourced row by construction, and
         * that refusal is what keeps this off the PHI surface decisions 421–423
         * closed.
         */
        public ?string $comment,
        /**
         * The reviewer's display name as Google publishes it, or null for an
         * anonymous review.
         *
         * Public information about a public review. Stored because a review
         * shown without attribution reads as fabricated, which is the opposite
         * of what `29` §2 rule 1 is protecting.
         */
        public ?string $authorName,
        public ?Carbon $createdAt,
        public ?Carbon $updatedAt,
        public bool $hasOwnerReply,
        /**
         * What the payload actually said about an owner reply, including
         * **nothing at all**.
         *
         * ⛔ **IT DEFAULTS TO `null` ON THE PROMOTED PARAMETER ITSELF, AND THE
         * DEFAULT IS THE SAFETY** (7001's shape). A constructor written next
         * year that does not think about this hands its consumer *"the payload
         * did not say"*, which is the answer that makes
         * `reconcileUnconfirmedPublication()` do nothing. The reckless value is
         * `false`, and `false` can only be reached by somebody writing it.
         */
        public ?bool $ownerReplyReported = null,
    ) {}

    /**
     * Build from one element of a Zernio `GET /inbox/reviews` response.
     *
     * Defensive on every field because this is a third party relaying a fourth:
     * a shape change at Google reaches us through Zernio's translation, and the
     * failure we must not have is a sync that throws on one malformed review and
     * abandons the page. Anything unreadable becomes null; only the id is
     * required, because a review with no stable id cannot be ingested
     * idempotently and is therefore not ingestible at all.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromZernio(array $payload): ?self
    {
        $id = $payload['id'] ?? null;

        if (! is_string($id) || $id === '') {
            return null;
        }

        $rating = $payload['rating'] ?? null;
        $rating = is_int($rating) && $rating >= 1 && $rating <= 5 ? $rating : null;

        $reply = $payload['reply'] ?? null;
        $hasReply = $payload['hasReply'] ?? null;

        return new self(
            externalId: $id,
            rating: $rating,
            comment: self::text($payload['text'] ?? $payload['comment'] ?? null),
            authorName: self::text(
                $payload['author']['name']
                    ?? $payload['reviewer']['name']
                    ?? $payload['reviewerName']
                    ?? null
            ),
            createdAt: self::time(
                $payload['created']
                    ?? $payload['createTime']
                    ?? $payload['createdAt']
                    ?? null
            ),
            updatedAt: self::time(
                $payload['updated']
                    ?? $payload['updateTime']
                    ?? $payload['updatedAt']
                    ?? null
            ),
            hasOwnerReply: ($hasReply === true)
                || (is_array($reply) && ($reply['id'] ?? null) !== null),
            // ⛔ A `false` HERE IS ONLY EVER THE VENDOR'S OWN `false`. Zernio
            // documents `hasReply` as a required boolean on both the list
            // response and the `review.new` / `review.updated` webhook payload
            // (raw `docs.zernio.com/llms-full.txt`, fetched 2026-08-21), so a
            // missing or non-boolean value is a payload we do not understand
            // rather than a review with no reply. ⚠️ **The `reply` object
            // cannot supply the negative**: its shape is documented as
            // `object,null` with no description anywhere in that corpus, so its
            // absence is as likely to be a mapping we cannot read as a listing
            // with nothing on it. It supplies the positive only.
            ownerReplyReported: match (true) {
                $hasReply === true => true,
                is_array($reply) && ($reply['id'] ?? null) !== null => true,
                $hasReply === false => false,
                default => null,
            },
        );
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function time(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            // A timestamp we cannot read is not a reason to drop a review. The
            // review is real; only our knowledge of when it landed is missing.
            return null;
        }
    }
}

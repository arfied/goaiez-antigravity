<?php

declare(strict_types=1);

namespace App\Services\Audit\Checks;

use App\Contracts\AuditCheck;
use App\Enums\AuditCheckKey;
use App\Services\Audit\AuditContext;
use App\Services\Audit\CheckResult;
use App\Services\Audit\Finding;
use App\Services\Config\DefaultsRegistry;
use App\Services\Places\PlaceSummary;

/**
 * `29` §6.2's second check: "review stats + reply-rate vs 3 nearby same-category
 * competitors", and the source of its named example finding — "12 reviews have
 * no reply".
 *
 * TWO NARROWINGS OF THE SPEC, BOTH RECORDED RATHER THAN SILENT.
 *
 * Decision 196: the comparison is aggregate and unnamed. "3 nearby businesses
 * average 4.6★" carries the finding; a competitor's name adds a defamation
 * surface to a page anyone can share, for nothing.
 *
 * Decision 212: competitor *reply-rate* is not computed at all. Reply-rate needs
 * `reviews`, an Atmosphere field, so doing it for three neighbours means three
 * extra Atmosphere calls per audit — about $900/month on its own, more than the
 * rest of the audit combined. The rating that Nearby Search already returns
 * carries the comparison.
 *
 * THE REPLY-RATE DENOMINATOR IS NOT THE REVIEW COUNT. Places returns a bounded
 * sample of reviews, not all of them — `userRatingCount` can be in the hundreds
 * while `reviews` holds five. So the sentence counts unanswered reviews *in what
 * Google returned* and says so. Reporting "487 reviews have no reply" from a
 * five-review sample would be a fabricated number, which is the one thing
 * `BUILD-PLAN` §2.5.3 asks this slice never to do.
 */
final class ReviewStatsCheck implements AuditCheck
{
    /**
     * Reply-rate at or above this reads as a business that answers its reviews.
     */
    private const float STRONG_REPLY_RATE = 0.8;

    /**
     * Below this, a business is effectively invisible in map results regardless
     * of how good it is. Ours, and stated as a consequence rather than a rule.
     */
    public const int THIN_REVIEW_COUNT = 10;

    public function __construct(private readonly DefaultsRegistry $defaults) {}

    public function thinReviewCount(): int
    {
        return $this->defaults->int('audit.reviews.thin_count');
    }

    public function key(): AuditCheckKey
    {
        return AuditCheckKey::ReviewStats;
    }

    public function run(AuditContext $context): CheckResult
    {
        $place = $context->place;

        if ($place === null) {
            return CheckResult::unavailable(
                $this->key(),
                $context->placeUnavailableReason ?? 'no_place',
            );
        }

        $findings = [];

        $countFinding = $this->reviewCountFinding($place);

        if ($countFinding instanceof Finding) {
            $findings[] = $countFinding;
        }

        $findings[] = $this->replyFinding($context);

        $nearbyFinding = $this->nearbyFinding($context);

        if ($nearbyFinding instanceof Finding) {
            $findings[] = $nearbyFinding;
        }

        return CheckResult::ran($this->key(), $findings);
    }

    /**
     * How many Google reviews this business has — or nothing at all, when the
     * only honest answer is that we do not know.
     *
     * ⛔ **THIS WAS `$place->userRatingCount ?? 0`, AND THE `?? 0` REACHED A
     * PUBLIC, UNAUTHENTICATED PAGE.** `POST /api/public/audit` is the free audit
     * a stranger runs by typing a business name, and
     * `PublicAuditResource::toArray()` passes `findings` straight out — so a
     * null review count did not produce an internal report that was wrong, it
     * produced **this platform telling somebody else's business, in public, that
     * it has no Google reviews.** `PlaceSummary` types the field `?int` on
     * purpose; one `??` at this call site threw the distinction away.
     *
     * ⚠️ **THE FETCH FAILURE WAS ALREADY GUARDED AND IS NOT WHAT THIS IS.**
     * `AuditContextBuilder::build()` turns a budget refusal, an unreachable
     * vendor and an unresolvable place into `placeUnavailableReason`, and
     * {@see self::run()} turns that into `CheckResult::unavailable()`. What was
     * unguarded is the far more ordinary case: a **successful** response with
     * one field missing from it.
     *
     * ⛔ **AND A MISSING FINDING IS THE RIGHT ANSWER RATHER THAN A HEDGED ONE.**
     * `FindingSeverity` has no `Unknown` case and says in writing that it cannot
     * have one — *"a finding exists because a check ran and produced it; 'we
     * could not look' is carried by CheckResult"* (decision 229). Nor may the
     * whole check go unavailable: reply-rate ran and is reportable, which is the
     * same argument {@see self::nearbyFinding()} already makes for the
     * neighbours. `AuditScore` counts only findings that exist, on **both** sides
     * of its ratio, so declining to say costs the business nothing.
     *
     * ⚠️ **WHAT THE VENDOR ACTUALLY DOES IS WHY THIS IS NOT SIMPLY "NULL MEANS
     * UNKNOWN"** — see {@see PlaceSummary::knownReviewCount()}. Google omits a
     * field holding its default value even when the mask asked for it, so an
     * absent count is usually a real zero, and refusing to report it would
     * silence this check's headline sentence for exactly the business the free
     * audit exists to find.
     */
    private function reviewCountFinding(PlaceSummary $place): ?Finding
    {
        $reviewCount = $place->knownReviewCount();

        if ($reviewCount === null) {
            return null;
        }

        // ⚠️ Carried on every one of the three findings, never only on the zero.
        // A field that appears exactly when the news is bad is a field a reader
        // learns to treat as the news.
        $source = ['review_count_source' => $place->reviewCountSource()];

        return match (true) {
            $reviewCount === 0 => Finding::critical(
                $this->key(),
                'reviews.none',
                'You have no Google reviews. This is the single biggest thing standing between you and the businesses above you in search results.',
                ['review_count' => 0, ...$source],
            ),
            $reviewCount < $this->thinReviewCount() => Finding::attention(
                $this->key(),
                'reviews.thin',
                sprintf(
                    'You have %d Google review%s. Below about %d, most people cannot tell whether a rating is real.',
                    $reviewCount,
                    $reviewCount === 1 ? '' : 's',
                    $this->thinReviewCount(),
                ),
                ['review_count' => $reviewCount, ...$source],
            ),
            default => Finding::healthy(
                $this->key(),
                'reviews.present',
                sprintf('You have %d Google reviews.', $reviewCount),
                ['review_count' => $reviewCount, ...$source],
            ),
        };
    }

    /**
     * The "12 reviews have no reply" finding, counted honestly over the sample
     * Google actually returned.
     */
    private function replyFinding(AuditContext $context): Finding
    {
        /** @var PlaceSummary $place */
        $place = $context->place;

        $rate = $place->replyRate();

        if ($rate === null) {
            // No reviews in the sample. Not "you reply to nothing" — there is
            // nothing to reply to, and PlaceSummary::replyRate() returns null
            // rather than 0.0 precisely so this stays a different sentence.
            return Finding::healthy(
                $this->key(),
                'reviews.replies_not_applicable',
                'There are no reviews to reply to yet.',
            );
        }

        $sampled = count($place->reviews);
        $unanswered = (int) round($sampled * (1 - $rate));

        if ($rate >= self::STRONG_REPLY_RATE) {
            return Finding::healthy(
                $this->key(),
                'reviews.replies_strong',
                sprintf('You reply to %d%% of your recent reviews.', (int) round($rate * 100)),
                ['reply_rate' => round($rate, 2), 'sampled' => $sampled],
            );
        }

        return Finding::critical(
            $this->key(),
            'reviews.replies_missing',
            sprintf(
                '%d of your %d most recent reviews have no reply. Replying is public, takes a minute, and is read by everyone deciding whether to call you.',
                $unanswered,
                $sampled,
            ),
            ['reply_rate' => round($rate, 2), 'unanswered' => $unanswered, 'sampled' => $sampled],
        );
    }

    /**
     * The unnamed aggregate comparison, or nothing at all.
     *
     * Returns null rather than an "unavailable" result, because the two review
     * findings above did run — losing the neighbours costs this check one
     * finding, not the whole thing. AuditScore averages over what exists.
     */
    private function nearbyFinding(AuditContext $context): ?Finding
    {
        /** @var PlaceSummary $place */
        $place = $context->place;

        $average = $context->nearbyAverageRating();
        $mine = $place->rating;

        if ($average === null || $mine === null) {
            return null;
        }

        $count = $context->nearbyCount();

        if ($mine >= $average) {
            return Finding::healthy(
                $this->key(),
                'reviews.rating_above_nearby',
                sprintf(
                    'Your %.1f★ rating is at or above the %.1f★ average of %d nearby businesses like yours.',
                    $mine,
                    $average,
                    $count,
                ),
                ['rating' => $mine, 'nearby_average' => round($average, 2), 'nearby_count' => $count],
            );
        }

        return Finding::attention(
            $this->key(),
            'reviews.rating_below_nearby',
            sprintf(
                '%d nearby businesses like yours average %.1f★. You are at %.1f★.',
                $count,
                $average,
                $mine,
            ),
            ['rating' => $mine, 'nearby_average' => round($average, 2), 'nearby_count' => $count],
        );
    }
}

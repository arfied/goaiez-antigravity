<?php

declare(strict_types=1);

namespace App\Support;

/**
 * An aggregate rating, and the count it was computed over.
 *
 * ⛔ **THIS OBJECT CARRIES NO FILTER AND MUST NEVER LEARN ONE.** `29` §2 rule 5
 * and §12.1 make *"never a filtered or 5-star-only aggregate"* a build-failing
 * claim, and decision 405 is the widget feed refusing to emit any average at
 * all rather than risk computing one over a filtered list. This is the other
 * answer to the same problem: a truthful average, computed in one place, by a
 * class that has no access to the display filters and therefore cannot apply
 * one by accident.
 *
 * ⚠️ **`count` IS PART OF THE CLAIM, NOT DECORATION.** An average with no
 * denominator is unfalsifiable — 4.9 over three reviews and 4.9 over three
 * hundred are the same string — and both `schema.org/AggregateRating` and the
 * FTC's 2024 Rule on Consumer Reviews treat the population as part of what is
 * being asserted. The two travel together so no caller can publish one without
 * the other.
 *
 * ⚠️ **`average` IS ROUNDED TO ONE DECIMAL AT CONSTRUCTION**, which is what
 * `locations.current_rating` stores (`DECIMAL(2,1)`) and what every review
 * platform publishes. Rounding here rather than in the view means the number a
 * human reads and the number in the JSON-LD are the same number by
 * construction, instead of two roundings that agree until somebody changes one.
 */
final readonly class RatingSummary
{
    private function __construct(
        public float $average,
        public int $count,
    ) {}

    /**
     * @param  list<int>  $ratings  Every rating in the population, unfiltered.
     */
    public static function over(array $ratings): ?self
    {
        $count = count($ratings);

        if ($count === 0) {
            // ⛔ **NO REVIEWS MEANS NO CLAIM, NOT A ZERO.** A `0.0` here would
            // render as a rating of zero out of five on a business that has
            // simply never been reviewed — a number we invented about somebody
            // else's business, published at a public address. Null is what the
            // callers turn into "no rating block at all".
            return null;
        }

        return new self(
            round(array_sum($ratings) / $count, 1),
            $count,
        );
    }
}

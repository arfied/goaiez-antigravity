<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

/**
 * One page's counts over one window.
 *
 * ⚠️ **COUNTS, NEVER A RATE.** A conversion rate computed here would be a float
 * in a file whose whole neighbourhood is integer-only for replay reasons, and it
 * would hide the denominator — which is the number a caller needs to decide
 * whether the question has an answer at all (3787's *"the reason string names
 * the denominator that was actually used"*).
 */
final readonly class PageTrafficTotals
{
    public function __construct(
        public int $pageviews,
        public int $conversions,
    ) {}
}

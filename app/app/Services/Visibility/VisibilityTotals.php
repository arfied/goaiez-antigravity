<?php

declare(strict_types=1);

namespace App\Services\Visibility;

use App\Services\Gsc\SearchAnalyticsResult;

/**
 * One window's Search Console numbers, summed.
 *
 * Only reachable through {@see VisibilityReading::totals()}, which refuses unless
 * the reading is `Measured` — so there is no way to hold this object and not know
 * that its numbers are real. That is the shape decision 1084 asks for: the
 * absence lives on the reading, never inside the metric.
 *
 * ⚠️ **CTR IS DERIVED, NOT STORED, AND GOOGLE'S OWN `ctr` IS DISCARDED.** The API
 * returns one, and keeping it would be a second source of truth for a value that
 * is exactly `clicks / impressions` — decision 286's finding, where one boolean
 * standing in for a per-channel fact was *lossy rather than merely denormalised*.
 * Worse here: our totals are summed across days, and the mean of Google's daily
 * CTRs is not the CTR of the summed window. Storing the returned figure would
 * produce a number that disagrees with the two numbers printed beside it.
 *
 * ⚠️ **AVERAGE POSITION IS NOT DERIVABLE AND IS NOT A RANK.** It survives because
 * nothing else can reconstruct it, and it is impression-weighted — decision 1085
 * keeps it off the normal surface for that reason as much as for `29` §2's
 * ranking rule. `28` §5.3.3 puts *"query positions (GSC)"* on Advanced only.
 *
 * ⚠️ **`final` IS THE HONESTY BIT.** Google's most recent days are still being
 * collected; see {@see SearchAnalyticsResult} for what the API
 * says about that and how it is read off the wire. A window containing an
 * unfinalised day is a number that will change, and reporting it as settled is
 * the same class of lie decision 1084 forbids one level up.
 */
final readonly class VisibilityTotals
{
    private function __construct(
        public int $clicks,
        public int $impressions,
        public float $averagePosition,
        public bool $final,
        public int $days,
    ) {}

    /**
     * The only factory.
     *
     * @param  list<array{clicks: int, impressions: int, position: float, final: bool}>  $days
     */
    public static function sum(array $days): self
    {
        $clicks = 0;
        $impressions = 0;
        $weighted = 0.0;
        $final = true;

        foreach ($days as $day) {
            $clicks += $day['clicks'];
            $impressions += $day['impressions'];

            // Impression-weighted, because that is what Google's own average
            // position is. A plain mean of daily positions gives a day with four
            // impressions the same say as a day with four thousand.
            $weighted += $day['position'] * $day['impressions'];

            $final = $final && $day['final'];
        }

        return new self(
            clicks: $clicks,
            impressions: $impressions,
            averagePosition: $impressions === 0 ? 0.0 : $weighted / $impressions,
            final: $final,
            days: count($days),
        );
    }

    /**
     * Clicks per impression, as a fraction.
     *
     * Zero impressions yields 0.0 rather than a division error. That is safe
     * *here* and would not be safe on a metric: a reading with no impressions is
     * `NoDataYet`, so nothing renders this at all.
     */
    public function clickThroughRate(): float
    {
        return $this->impressions === 0 ? 0.0 : $this->clicks / $this->impressions;
    }
}

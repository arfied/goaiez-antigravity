<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Enums\FindingSeverity;

/**
 * The number on the dial.
 *
 * `29` §6.2 puts a "score dial (the Status Instrument, reused)" at the top of
 * the result and never says how the number is produced, so the model is ours.
 * Three properties were required of it, and each one rules something out:
 *
 * DETERMINISTIC (`BUILD-PLAN` §2.5.3 — "scoring is deterministic for a fixed
 * fixture"). No weighting by anything outside the findings themselves, no
 * randomness, no clock, no comparison against a moving population. The same
 * findings always produce the same number, which is what makes a re-run
 * meaningful — the free tier's whole pitch is "Boost Score and trend" (§5.2),
 * and a trend line built on a drifting formula measures the formula.
 *
 * HONEST ABOUT WHAT IT DID NOT SEE. The denominator counts only findings that
 * exist. A check that could not run contributes nothing to either side of the
 * ratio, so a business is never marked down because our Places budget was
 * exhausted or because its host was behind a bot challenge. The alternative —
 * scoring an unavailable check as zero — makes the number partly a report on our
 * own infrastructure, and a visitor cannot tell which part.
 *
 * NOT A RANKING CLAIM. `29` §2 forbids claiming guaranteed rankings, and a score
 * that implied a search position would be exactly that in a different font. This
 * measures how complete and consistent a business's public information is, which
 * is a fact about the business rather than a prediction about Google.
 *
 * ROUNDING IS HALF-UP AND THE FLOOR IS ZERO, not "start at 100 and deduct".
 * Deduction models bottom out at negative numbers on genuinely neglected
 * listings, and clamping a negative to zero throws away the difference between
 * bad and catastrophic while the ratio keeps it.
 */
final class AuditScore
{
    /**
     * The score for a set of check results, or null when nothing could be
     * measured at all.
     *
     * Null rather than zero is the same distinction CheckResult draws one level
     * down: zero means "we looked and it is terrible", null means "we could not
     * look", and the second one must never render as a dial reading nought.
     *
     * @param  list<CheckResult>  $results
     */
    public static function from(array $results): ?int
    {
        $earned = 0;
        $possible = 0;

        foreach ($results as $result) {
            if (! $result->ran) {
                continue;
            }

            foreach ($result->findings as $finding) {
                $earned += $finding->severity->points();
                $possible += FindingSeverity::maxPoints();
            }
        }

        if ($possible === 0) {
            return null;
        }

        return (int) round($earned / $possible * 100);
    }

    /**
     * How many checks produced a result, for the "we checked N of 4 things"
     * line the public page needs in order to be honest about coverage.
     *
     * @param  list<CheckResult>  $results
     */
    public static function checksRan(array $results): int
    {
        return count(array_filter(
            $results,
            static fn (CheckResult $result): bool => $result->ran,
        ));
    }

    /**
     * Total problems found — the "we found N things" number `29` §6.2's third
     * use reuses as "we found and fixed N things" in the first-week engine.
     *
     * @param  list<CheckResult>  $results
     */
    public static function problemCount(array $results): int
    {
        return array_sum(array_map(
            static fn (CheckResult $result): int => $result->problemCount(),
            $results,
        ));
    }
}

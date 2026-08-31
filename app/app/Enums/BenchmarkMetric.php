<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What L3 publishes a cohort distribution of, and in what unit.
 *
 * `GOAIEZ_PIXEL_MASTER_BUILD` §5.5's `benchmark_cohort_daily` is keyed
 * `(cohort_key, date, metric)` and carries `p25/p50/p75`. This enum is the
 * `metric` vocabulary, and — as with [[WebVital]] and the vitals mart — the
 * database's CHECK constraint is built from these cases, so the two cannot
 * drift without `WarehouseTest` saying so.
 *
 * ---------------------------------------------------------------------------
 * ⛔ EVERY VALUE IS AN INTEGER IN A DECLARED UNIT, AND THAT IS A REPLAY RULE
 * ---------------------------------------------------------------------------
 * §5.5's own DDL writes `p25 Float64`. **A float is forbidden in a derived
 * table here** — `WarehouseTest`'s reproducible-DDL lint refuses `double
 * precision` and `real` outright, because floating-point addition is not
 * associative and the same rows summed in a different order produce different
 * bits. So a conversion *rate* is not stored as `0.037`; it is stored as **37
 * per mille**, an exact integer, and the report that renders it divides.
 *
 * ⚠️ **INTEGER DIVISION, FLOOR, AND IT IS NAMED RATHER THAN INHERITED.**
 * `intdiv()` truncates toward zero and every numerator here is non-negative, so
 * floor and truncation are the same thing. Rounding would need a tie rule, and
 * a tie rule is one more thing that has to be the same on the machine that
 * replays this in a year.
 *
 * ⚠️ **A NULL IS "THIS TENANT DOES NOT CONTRIBUTE TO THIS METRIC", NOT A
 * ZERO.** A business with no sessions on a day has no conversion *rate* — it has
 * no denominator — and counting it as 0% would drag every cohort's p25 toward
 * zero with the arithmetic of tenants who were closed that day. It contributes
 * to `sessions` and to nothing else, which is why `tenant_count` is stored **per
 * metric row** rather than per cohort.
 */
enum BenchmarkMetric: string
{
    /**
     * Sessions in the day, bot traffic already excluded by `l2_fact_session`.
     *
     * ⚠️ A count rather than a rate, and the only one here. It is the metric a
     * tenant most wants ("is my traffic normal for my trade?") and it is also
     * the coarsest disclosure in this layer — see `NetworkBenchmarks`' docblock
     * on what somebody holding the whole of L3 can and cannot learn.
     */
    case Sessions = 'sessions';

    /**
     * Conversions per 1,000 sessions.
     */
    case ConversionRate = 'conversion_rate';

    /**
     * Engaged sessions per 1,000 sessions. §8's engagement rule is applied once,
     * at `l2_fact_session.is_engaged`, and read back here rather than
     * re-derived — the same reasoning `l2_fact_daily_tenant` gives for its own
     * session-grain columns.
     */
    case EngagedSessionRate = 'engaged_session_rate';

    /**
     * Pageviews per 100 sessions.
     */
    case PageviewsPerSession = 'pageviews_per_session';

    /**
     * The unit `p25`/`p50`/`p75` are stored in, for whoever reads the table.
     */
    public function unit(): string
    {
        return match ($this) {
            self::Sessions => 'sessions',
            self::ConversionRate, self::EngagedSessionRate => 'per mille of sessions',
            self::PageviewsPerSession => 'per hundred sessions',
        };
    }

    /**
     * One tenant's contribution to this metric for one day, or null when the
     * tenant does not contribute to it at all.
     *
     * ⚠️ **THE DENOMINATOR TEST IS `> 0` AND NOT `>= 0`**, so a day with no
     * sessions yields null for every rate. See the class docblock: a missing
     * rate is not a rate of zero, and the difference moves every percentile in
     * the cohort.
     */
    public function valueFor(int $sessions, int $engagedSessions, int $conversions, int $pageviews): ?int
    {
        return match ($this) {
            self::Sessions => $sessions,
            self::ConversionRate => $sessions > 0 ? intdiv($conversions * 1000, $sessions) : null,
            self::EngagedSessionRate => $sessions > 0 ? intdiv($engagedSessions * 1000, $sessions) : null,
            self::PageviewsPerSession => $sessions > 0 ? intdiv($pageviews * 100, $sessions) : null,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}

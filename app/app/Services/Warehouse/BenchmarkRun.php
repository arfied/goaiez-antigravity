<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use Carbon\CarbonImmutable;

/**
 * What one day's L3 derivation did.
 *
 * ⚠️ **RETURNED RATHER THAN FILED, AND THE ABSENCE OF AN `etl_runs` ROW IS A
 * DECISION** (5945). `etl_runs` is tenant-owned — its own creating migration
 * argues for that at length, because a run's counts are one business's numbers —
 * and this run is a function of *every* tenant, so it has no `business_id` to
 * file under and no honest one to invent. A `NULL` there would be refused by the
 * table's own `WITH CHECK`, and widening that policy to admit a platform row
 * would make every tenant's `etl_runs` reader see it.
 *
 * ⛔ **SO THE PRODUCTION HALF OF `etl_runs` IS NOT REPLICATED HERE AND IS OWED**:
 * an operator cannot compare today's L3 digest against last week's, because
 * nothing stores it. `warehouse:benchmark` prints it. Where a platform-scoped
 * ETL bookkeeping row should live is a question this slice raises rather than
 * answers — inventing a second `etl_runs` for one caller is the shape that ends
 * with two tables answering one question.
 *
 * ⚠️ **THE FOUR EXCLUSION COUNTS ARE THE POINT OF THIS OBJECT, NOT DECORATION.**
 * On any real deployment today `withoutRecognisedVertical` is the whole install
 * base — see [[\App\Enums\BenchmarkVertical]] — so a run publishes nothing.
 * "Nothing published" and "nothing to publish" look identical without these, and
 * `CLAUDE.md`'s recurring finding is exactly that: a layer that is silently
 * empty reads as a layer that is working.
 */
final readonly class BenchmarkRun
{
    public function __construct(
        public CarbonImmutable $day,
        /** The k actually applied — the registry's, never below the floor. */
        public int $minimumCohort,
        /** Cohort rows written. */
        public int $published,
        /** Cohort/metric pairs that had contributors and fewer than k of them. */
        public int $suppressed,
        /** Businesses that contributed at least one measurement. */
        public int $contributing,
        /** Businesses whose stored vertical is not a [[\App\Enums\BenchmarkVertical]]. */
        public int $withoutRecognisedVertical,
        /** Businesses with a recognised vertical and no L2 row for the day. */
        public int $withoutMeasurements,
        /** The whole layer's bytes, digested — see [[WarehouseSnapshot::networkDigest()]]. */
        public string $digest,
    ) {}
}

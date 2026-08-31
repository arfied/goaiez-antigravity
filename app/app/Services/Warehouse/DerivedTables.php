<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

/**
 * Every table the derived warehouse owns, written down once.
 *
 * ⛔ **LIFTED OUT OF `PhiExclusion` ON 2026-08-30 (12536, 12538), AND THE LIFT
 * IS THE WHOLE POINT OF THE SLICE THAT DID IT.** The owner overrode `29` §2 rule
 * 24 and its §12.1 line, which retires the PHI machinery — and a scout found
 * that the class named for that rule was **also** the retention sweep's table
 * list. `WarehouseRetention` iterates it, `TableHorizons` derives its horizons
 * from it, `PruneWarehouse` defers to it and four test files read it. **Deleting
 * `PhiExclusion` with the list still inside it would have deleted a
 * data-retention obligation that has nothing to do with PHI**, and it would have
 * done so quietly: `docs/COMMERCIAL-MODEL.md` §3.4 already records that *a gate
 * checking a sweep is scheduled is satisfied by a scheduled no-op*, so a sweep
 * that lost its subject would go on reporting healthy.
 *
 * ⚠️ **A CLASS NAMED FOR ONE RULE WAS LOAD-BEARING FOR ANOTHER** — 272's shape
 * inverted. Not a table with no reader, but a reader nobody would think to look
 * for. **The name is now the subject**, so the next person deleting a compliance
 * class can see what else is standing on it.
 *
 * ⛔ **THE LISTS ARE UNCHANGED, TO THE STRING.** This is a move and not an edit:
 * a lift that also corrected something would make the diff unreviewable, and
 * both `WarehouseTest` lints below compare these against `pg_tables` on every
 * run, so a silent change would have failed the build rather than been noticed.
 */
final class DerivedTables
{
    /**
     * Every table the derived warehouse owns, by layer.
     *
     * ⛔ **THIS CONSTANT EXISTS BECAUSE A PURGE WRITTEN AGAINST A WAREHOUSE WITH
     * TWO TABLES WAS MERGED INTO ONE THAT HAS SIX** (5130). W26 wrote the purge
     * naming `l1_events` and `l2_fact_source_daily`, which was the whole of the
     * derived warehouse on its own branch; W23 added `l2_fact_session`,
     * `l2_fact_conversion`, `l2_fact_daily_tenant` and `l2_fact_page_daily` on
     * another branch, each with its writer in {@see Replayer}. **Both lanes were
     * green and the composed tree left rows in four of the six tables** — a
     * failure produced by nothing either lane could run. `WarehouseTest`
     * compares this list against `pg_tables` so the seventh mart cannot repeat
     * it.
     *
     * ⚠️ **AN EXPLICIT LIST HERE AND A DERIVED ONE IN THE LINT, DELIBERATELY.**
     * A sweep that enumerated `pg_tables` at runtime would delete from whatever
     * happened to be named `l2_…` on the day it ran, which is a `DELETE` whose
     * subject nobody wrote down. The list is written; the lint is what stops it
     * going stale.
     *
     * @var array{l1: list<string>, l2: list<string>}
     */
    public const DERIVED_TABLES = [
        'l1' => [
            'l1_events',
        ],
        'l2' => [
            'l2_fact_conversion',
            'l2_fact_daily_tenant',
            'l2_fact_page_daily',
            'l2_fact_session',
            'l2_fact_source_daily',
            'l2_fact_vital_daily',
        ],
    ];

    /**
     * The derived tables that carry no `business_id`, so cannot be swept by one.
     *
     * ⛔ **A SEPARATE CONSTANT BECAUSE THE STATEMENT IS DIFFERENT, NOT BECAUSE
     * L3 IS LESS IN SCOPE.** Every table above is deleted
     * `WHERE business_id = ?`; L3 has no such column by schema — that is rule 1
     * of the layer — so a loop that treated it like the others would raise on a
     * column that is not there. `WarehouseTest`'s lint compares **both**
     * constants together against `pg_tables`, so a seventh mart or a second L3
     * table still cannot ship unswept.
     *
     * @var array{l3: list<string>}
     */
    public const NETWORK_TABLES = [
        'l3' => [
            'l3_benchmark_cohort_daily',
        ],
    ];
}

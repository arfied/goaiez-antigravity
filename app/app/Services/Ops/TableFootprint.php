<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Console\Commands\ShowStorageFootprint;
use App\Console\Commands\ShowTableFootprint;
use App\Services\Config\DefaultsRegistry;
use App\Support\TableHorizons;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Every table in this schema, its size, its write shape and its horizon -
 * decisions 8000-8019.
 *
 * ## ⛔ NO TENANT AND NO OWNER WALK - WHICH IS THE LOAD-BEARING PROPERTY RATHER
 * THAN AN OPTIMISATION
 *
 * ⚠️ **THIS HEADING READ *"ONE QUERY"* UNTIL 2026-08-28 AND THERE ARE NOW FIVE
 * — wave 41 lane D (11072).** The catalog read below is still one statement;
 * beside it sit one `platform_settings` lookup per registry key a horizon's
 * period lives in, {@see TableHorizons::periodKeys()}, four of them today.
 * **The tenant property is untouched and is the half that was ever
 * load-bearing**: `platform_settings` is platform-wide reference data and is a
 * named exemption from row-level security in `TenancyTest`'s census, so a read
 * from a console process with no tenant established returns the row rather
 * than nothing. ⛔ **If it ever gains RLS, every one of these answers `0`, the
 * report calls five bounded tables unbounded and nothing here would notice** —
 * which is the conservative direction and still wrong, so the lint in
 * `RetentionTest` drives the predicate with a period **set** as well as unset.
 *
 * Commands in this application walk every user and every business they own,
 * because a range statement against a FORCE row-level-secured table from a
 * console process **matches zero rows and exits 0** (7626, and 7785(d) where a
 * method's docblock claimed otherwise for eleven months). ⚠️ **THE COUNT IS
 * DELETED RATHER THAN CORRECTED — 2026-08-28 (11262).** This sentence said
 * *"seven commands"* in four places in this tree — here, {@see
 * ShowTableFootprint}, `MailQuota::prune()` and `TableFootprintTest` — and the
 * true figure is **38**. **Four copies of one number is the point**: the
 * sentence is a contrast, the walk is what carries it, and the number was
 * never doing any work (8861). **Nothing here is
 * subject to that**, and it is worth saying why rather than leaving the absence
 * of a walk to be read as an omission: `pg_total_relation_size()` and
 * `pg_stat_user_tables` are catalog and statistics reads. They describe the
 * relation, not its rows, and no policy applies to them. Decision 3133 settled
 * a production data question with exactly these two sources, on exactly this
 * property.
 *
 * ⛔ **SO A `count(*)` MAY NEVER BE ADDED TO THIS CLASS.** It is the obvious
 * improvement - the row estimate below is only an estimate - and it would turn
 * a query that needs no tenant into one that silently returns zero for 112 of
 * this schema's 161 tables while every other column stayed right. The estimate
 * is honest about being an estimate; a `0` next to a table holding four million
 * rows is not.
 *
 * ## ⚠️ IT HAS NO TENANT DIMENSION AND MUST NEVER GAIN ONE
 *
 * Decision 569's wall, in the shape {@see ShowStorageFootprint}
 * meets it from the other side: that command names **one account** and refuses a
 * leaderboard, because a per-tenant total readable across tenants is a leak
 * rather than a metric. This one has the opposite shape and is safe for the
 * opposite reason - it reports **per table across all tenants**, so no figure
 * here can be attributed to an account at all. **A `GROUP BY business_id` would
 * turn it into the leaderboard 569 refuses**, in a class that needs no tenant
 * and would therefore not fail closed the way the rest of this application
 * does.
 */
final class TableFootprint
{
    public function __construct(private readonly DefaultsRegistry $defaults) {}

    /**
     * Every table in the current schema, largest first.
     *
     * ⚠️ **`relkind IN ('r','p')` MATCHES `TenancyTest`'s CENSUS DELIBERATELY**,
     * so the two lints are looking at the same population and a table can never
     * be argued about in one and invisible to the other. A partitioned parent
     * (`p`) reports its own size and not its partitions' - and the partitions
     * are ordinary `r` relations in the same schema, so they are listed
     * separately and nothing is double counted or lost. There are none today.
     *
     * @return list<TableFootprintLine>
     */
    public function all(): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException(
                'The table footprint is read from PostgreSQL catalogs and has no other implementation. '
                .'The connection is currently "'.DB::connection()->getDriverName().'".'
            );
        }

        $horizons = TableHorizons::all();
        $stated = $this->statedPeriods();

        $rows = DB::select(
            <<<'SQL'
            SELECT c.relname                        AS table_name,
                   pg_total_relation_size(c.oid)    AS bytes,
                   COALESCE(s.n_live_tup, 0)        AS live_rows,
                   COALESCE(s.n_tup_ins, 0)         AS inserts,
                   COALESCE(s.n_tup_upd, 0)         AS updates,
                   COALESCE(s.n_tup_del, 0)         AS deletes,
                   GREATEST(s.last_analyze, s.last_autoanalyze) AS analyzed_at
              FROM pg_class c
              JOIN pg_namespace n ON n.oid = c.relnamespace
              LEFT JOIN pg_stat_user_tables s ON s.relid = c.oid
             WHERE n.nspname = current_schema()
               AND c.relkind IN ('r', 'p')
             ORDER BY pg_total_relation_size(c.oid) DESC, c.relname
            SQL
        );

        // array_values because DB::select() answers an array rather than a list,
        // and a caller reading the first line as "the largest" needs the keys to
        // be the order.
        return array_values(array_map(
            function (object $row) use ($horizons, $stated): TableFootprintLine {
                /** @var object{table_name: string, bytes: int|string, live_rows: int|string, inserts: int|string, updates: int|string, deletes: int|string, analyzed_at: string|null} $row */
                $table = (string) $row->table_name;
                $horizon = $horizons[$table] ?? null;
                $key = $horizon['key'] ?? null;

                return new TableFootprintLine(
                    table: $table,
                    bytes: (int) $row->bytes,
                    liveRows: (int) $row->live_rows,
                    inserts: (int) $row->inserts,
                    updates: (int) $row->updates,
                    deletes: (int) $row->deletes,
                    analyzedAt: $row->analyzed_at === null ? null : (string) $row->analyzed_at,
                    horizon: $horizon,
                    statedDays: $key === null ? null : ($stated[$key] ?? null),
                );
            },
            $rows
        ));
    }

    /**
     * The period an operator has actually stated, per registry key a horizon
     * names - wave 41 lane D (11072).
     *
     * ⛔ **`intOr($key, 0)` AND NEVER `value()`, WHICH IS THE FOUR PRUNERS'
     * OWN ACCESSOR.** `DefaultsRegistry::value()` funnels through `seedOf()`,
     * which raises `WithheldRegistryValue` for a figure the owner has not set -
     * correct on a price and wrong here, where *"nobody has set one"* is the
     * answer being asked for rather than a fault. `intOr()` asserts the key is
     * declared in the manifest (so a typo still raises) and then reads the row.
     *
     * ⛔ **`<= 0` IS "UNSET", NOT `< 0`.** All four pruners read the same row
     * the same way and treat `0` as no period, because a zero-day retention
     * would delete everything on the first sweep - `AutomationRunRetention::
     * retentionDays()` carries the argument. A report that called `0` a stated
     * period would call a table bounded that nothing deletes from, which is the
     * defect this method exists to close, one value along.
     *
     * ⚠️ **THE NUMBER IS THE ROW'S AND NOT THE EFFECTIVE ONE, AND THE ONE
     * PLACE THAT DIFFERS SAYS SO IN ITS OWN `keeps` TEXT.**
     * `GoogleRatingSnapshotRetention::retentionDays()` clamps a stated period
     * up to `review_loss.pause_flat_days` plus a week, so the figure printed
     * beside `google_rating_snapshots` can be shorter than what is really kept.
     * Resolving each key through its owning class instead would be a `match`
     * over four class names in a file whose whole argument is that it lists
     * nothing - and the clamp is disclosed in that entry's own horizon text.
     * **What must not differ is the boundedness answer, and a clamp can only
     * lengthen a period that has already been stated.**
     *
     * @return array<string, int|null>
     */
    private function statedPeriods(): array
    {
        $periods = [];

        foreach (TableHorizons::periodKeys() as $key) {
            $days = $this->defaults->intOr($key, 0);

            $periods[$key] = $days > 0 ? $days : null;
        }

        return $periods;
    }

    /**
     * The tables nothing on a clock deletes a row from, largest first.
     *
     * ⛔ **DERIVED, NEVER LISTED.** This is the answer three waves of briefs
     * carried as a hand-written list of thirteen names that had never been the
     * population - `docs/FAILURE-SHAPES.md`'s own *"the count in this bullet is
     * not a tally of the shape and should not be read as one"*, arriving at the
     * schema. A
     * table created next month is in this result the day its migration runs,
     * and nobody has to notice it.
     *
     * ⚠️ **IT IS NOT A LIST OF DEFECTS AND {@see ShowTableFootprint} SAYS SO IN
     * ITS OUTPUT.** Most of this schema belongs here: a row per business, per
     * user, per plan or per phone number is bounded by the thing it describes,
     * and a clock that deleted one would be deleting the customer's account.
     *
     * ⚠️ **THREE POPULATIONS ARE IN HERE AND ONLY THE REPORT TELLS THEM
     * APART** - wave 41 lane D (11070). A table with no horizon at all; a table
     * whose horizon takes only its bytes; and, since this slice, a table whose
     * sweep is scheduled with **no period set**, which deletes nothing until
     * somebody types a number into Ops.
     * {@see TableFootprintLine::periodIsUnset()} is the one that separates the
     * third, and `db:footprint` prints it as its own count rather than letting
     * it read as either neighbour.
     *
     * @return list<TableFootprintLine>
     */
    public function withoutRowHorizon(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (TableFootprintLine $line): bool => ! $line->rowsAreBounded(),
        ));
    }
}

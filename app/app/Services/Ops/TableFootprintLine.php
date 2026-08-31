<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Enums\RetentionScope;

/**
 * One table, as the database describes it - decision 8002.
 *
 * ⚠️ **ONE FIELD HERE IS EXACT AND THE REST ARE ESTIMATES, AND THE SPLIT IS
 * WORTH KNOWING BEFORE ANY OF THEM IS QUOTED.** {@see self::$bytes} comes from
 * `pg_total_relation_size()`, which measures files on disk. The three write
 * counters and {@see self::$liveRows} come from the statistics collector, which
 * is reset when a table is rebuilt, and which counts a transaction that later
 * rolled back as a write that happened. **That is not a defect to correct** -
 * for the question this is asked for, *"is this table written per event or per
 * thing?"*, an insert that rolled back is still evidence of the shape.
 */
final readonly class TableFootprintLine
{
    /**
     * @param  string  $table  the relation name in `current_schema()`
     * @param  int  $bytes  heap, indexes, TOAST and its indexes - exact
     * @param  int  $liveRows  the planner's estimate, only as fresh as the last
     *                         (auto)analyze, and `0` on a table that has never
     *                         had one
     * @param  int  $inserts  lifetime, including rolled-back transactions
     * @param  int  $updates  lifetime, as above
     * @param  int  $deletes  lifetime, as above
     * @param  string|null  $analyzedAt  when the row estimate was last refreshed,
     *                                   or null if it never was
     * @param  array{command: string, keeps: string, scope: RetentionScope, key: string|null}|null  $horizon
     *                                                                                                        what removes from this table on a clock,
     *                                                                                                        or null if nothing does
     * @param  int|null  $statedDays  the period an operator has set in the
     *                                registry row `$horizon['key']` names, or
     *                                null when nobody has set one — **and also
     *                                null when the period is a constant in code
     *                                rather than a setting.** The two are told
     *                                apart by `$horizon['key']` and never by
     *                                this field alone
     */
    public function __construct(
        public string $table,
        public int $bytes,
        public int $liveRows,
        public int $inserts,
        public int $updates,
        public int $deletes,
        public ?string $analyzedAt,
        public ?array $horizon,
        public ?int $statedDays = null,
    ) {}

    /**
     * Whether anything on a clock removes rows from this table.
     *
     * ⛔ **A BYTES HORIZON ANSWERS `false` HERE**, which is the distinction
     * {@see RetentionScope} exists for: `storage:prune` empties the bucket and
     * leaves the row, so the row count is as unbounded as a table with no
     * pruner at all.
     *
     * ⛔ **AND SO DOES A HORIZON WHOSE PERIOD NOBODY HAS SET — wave 41 lane D
     * (11070).** This method read the scope and nothing else until that slice,
     * so it answered `true` — **bounded** — for five tables whose sweeps have
     * never deleted a row on any deployment that has ever existed: their period
     * is a registry row with **no seed**, and their pruners open no query at
     * all while it is empty. `CLAUDE.md`'s own rule is the standard — *an unset
     * period is a NO-OP and not a zero* — and 272's is the shape: **a seeded
     * figure with no reader is a row in a table, not a ceiling**, which runs
     * identically for a scheduled command with no figure to read.
     *
     * ⚠️ **THE GATE THAT EXISTS TO CATCH EXACTLY THIS CHECKS THE SCHEDULE.**
     * 10835 states it as *"a sweep that is never invoked bounds nothing, and
     * db:footprint would report its tables as bounded"* — **and a scheduled
     * no-op satisfies it.** Being invoked and having something to sweep by are
     * two conditions; this report now asks both.
     */
    public function rowsAreBounded(): bool
    {
        return $this->horizon !== null
            && $this->horizon['scope'] === RetentionScope::Rows
            && ! $this->periodIsUnset();
    }

    /**
     * Whether something sweeps this table on a clock and nothing tells it what
     * to sweep by.
     *
     * ⚠️ **A THIRD STATE, AND NOT A DEFECT.** *Bounded*, *swept with no period
     * set* and *nothing sweeps this at all* are three answers and this report
     * printed two. The middle one is a decision that has not been made rather
     * than one that was got wrong — every one of these periods is deliberately
     * unseeded, on the ground `storage.retention_days.*` states, which is that
     * the drafted privacy policy commits to nothing about these kinds and
     * counsel has the question. **So it is reported as its own state**, rather
     * than folded in with a table nobody ever wrote a pruner for and rather
     * than printed as an error.
     *
     * ⛔ **IT IS FALSE FOR A HORIZON WHOSE PERIOD IS A CONSTANT.** Those carry
     * `key => null` and cannot be unset by anybody, so asking the registry
     * about them would be asking about a row that does not exist.
     */
    public function periodIsUnset(): bool
    {
        return $this->horizon !== null
            && $this->horizon['key'] !== null
            && $this->statedDays === null;
    }

    /**
     * The share of lifetime writes that were rewrites of an existing row.
     *
     * ⚠️ **THIS IS THE ONE MEASUREMENT THAT SEPARATES A TABLE WORTH ARGUING
     * ABOUT FROM A TABLE THAT IS FINE.** A row per business, per location or
     * per subscription is written once and then rewritten for years, so its
     * updates dwarf its inserts and its row count is bounded by the population
     * it describes. A row per event is inserted and never touched again, so
     * this is zero and the count rises with time and traffic for ever. Measured
     * on the install being asked, rather than classified by anybody in a list -
     * which is the whole reason the answer stops depending on somebody having
     * written the table down.
     *
     * @return int|null basis points, or null on a table nothing has ever
     *                  written - where the shape is genuinely unknown and a `0`
     *                  would read as "pure event table"
     */
    public function rewriteRateBp(): ?int
    {
        $writes = $this->inserts + $this->updates;

        if ($writes <= 0) {
            return null;
        }

        return intdiv($this->updates * 10_000, $writes);
    }

    /**
     * The share of everything ever inserted here that has since been removed.
     *
     * ⛔ **THIS FIELD WAS FETCHED, DOCUMENTED AND READ BY NOTHING UNTIL
     * 2026-08-23 - 272's shape inside the report whose subject is what a table
     * holds** (8750-8779). `n_tup_del` was selected in the query, passed to the
     * constructor and described in the docblock above, and no caller anywhere
     * in `app/` or `tests/` ever looked at it. **A counter with no reader is
     * the thing this whole slice exists to find**, and it was in the finder.
     *
     * ⛔ **IT IS THE ONE COLUMN ON THIS REPORT THAT CAN TELL A HORIZON THAT
     * WORKS FROM A HORIZON THAT IS MERELY SCHEDULED.** `SendingHealth::prune()`
     * spent eleven months documented as sweeping a table while deleting
     * nothing (7785(d)); a `0%` beside a table `TableHorizons` claims to bound
     * is that failure visible in one glance. Every other column would have read
     * identically throughout.
     *
     * ⚠️ **AND IT IS THE ONLY ONE THAT WORKS ON A FRESH INSTALL.** `liveRows`
     * is `n_live_tup`, which reads `0` on a table that has never been analysed
     * whatever it holds - the command warns about exactly that in its own
     * output. These two counters need no ANALYZE, so on a machine nobody has
     * ever run one on this is the only thing here that means anything.
     *
     * ⚠️ **IT CAN EXCEED 100% AND THAT IS NOT A BUG TO CLAMP.** The statistics
     * collector is reset when a table is rebuilt, so a table loaded before a
     * reset and drained after it reports more deletes than inserts. Read it as
     * a shape, the way the report says to read the other two.
     *
     * @return int|null basis points, or null on a table nothing has ever
     *                  inserted into - where a `0` would read as "nothing here
     *                  is ever removed" about a table nothing has exercised
     */
    public function removalRateBp(): ?int
    {
        if ($this->inserts <= 0) {
            return null;
        }

        return intdiv($this->deletes * 10_000, $this->inserts);
    }
}

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A session becomes one row — the owner's ruling 8040, with 8041 beside it.
 *
 * ⛔ **WHAT THE RULING SAYS, AND WHY A KEY IS THE WHOLE OF IT.** `l2_fact_session`
 * was keyed `(business_id, day, session_id)` with `day` derived as
 * `date_trunc('day', min(received_at))` **over the replayed range**, so the same
 * L0 produced a different table depending on how the range was cut: a session
 * whose beacons straddle UTC midnight is one row when both days are replayed
 * together and **two** when they are replayed a day at a time (7841). Switching
 * clocks does not fix that — both candidates are per-event days taken inside the
 * range, so a different clock moves the split rather than removing it (7930).
 * **Taking the day off the key makes the split unrepresentable** (7933(C)), which
 * is `GOAIEZ_PIXEL_MASTER_BUILD` §5.4's own shape: `fact_session` is
 * `ORDER BY (tenant_id, started_at, session_id)` on a `ReplacingMergeTree`, one
 * row per session, with no date column at all. `day` was this repository's
 * addition (7931).
 *
 * ⚠️ **`business_id` STAYS FIRST AND THE SPEC'S OWN ORDER WOULD FAIL A LINT.**
 * §5.4 writes `(tenant_id, started_at, session_id)`; `WarehouseTest`'s *"every
 * tenant-owned derived table is keyed on `business_id` first"* is unmoved by that
 * — a btree is usable on a leftmost prefix, and 6182's cross-tenant loss on
 * `l1_events` is what the rule was written from. `(business_id, session_id)` is
 * the same grain with a usable key.
 *
 * ## ⛔ `day` IS REPLACED BY `first_received_at`, AND THAT IS NOT THE SAME COLUMN
 * UNDER A NEW NAME
 *
 * The difference is what may be done with it. `day` was **in the primary key**,
 * so a session belonged to a day as a matter of identity and two cuts could mint
 * two identities. `first_received_at` is a `timestamp(3)` fact about the session —
 * the earliest receipt this platform holds for it — that **nothing keys on and
 * nothing groups by**. 8040's own sentence is the requirement: the daily rollups
 * *"must attribute a session to a day at **one** place instead of having it baked
 * into a primary key"*, and that one place is
 * [[\App\Services\Warehouse\Replayer]]'s `SESSION_ROLLUP_DAY`, which applies
 * 8041 — the day the session **started** — to this column.
 *
 * ⛔ **A SERVER CLOCK RATHER THAN `started_at`, AND THE ARGUMENT IS ALREADY
 * WRITTEN IN THE FILE THAT NEEDED IT.** `started_at` is `min(occurred_at)`:
 * client-controlled, and unbounded — [[\App\Services\Warehouse\L1Derivation]]
 * parses whatever `strtotime()` accepts and nothing constrains the result. Both
 * [[\App\Services\Warehouse\WarehouseRetention]]'s `columnFor()` and
 * `Replayer::rebuildL1()` refuse `occurred_at` for one reason apiece and they are
 * the same reason: *"a browser with a wrong clock would put its events outside
 * every range that could ever remove them — a row that is permanently
 * unexpirable because somebody's laptop is set to 2041."* A mart horizon keyed on
 * `started_at` inherits that exactly, in both directions — a clock in 2041 is a
 * row nothing can ever sweep, and one in 2001 is a row swept the day it is
 * written. **So the horizon needs a receipt time, and after `day` goes there is
 * no other server clock on this row.**
 *
 * ⚠️ **AND IT WAS ALREADY BEING COMPUTED.** `rebuildFactSession()`'s `agg` CTE has
 * carried `min(received_at) AS first_received_at` all along, with exactly two
 * consumers — the `day` projection and `is_new`'s `NOT EXISTS`. Storing it adds
 * no derivation and removes the keystroke that would have taken `is_new`'s
 * predicate out with the `day` projection.
 *
 * ## ⚠️ THE BACKFILL IS LOSSLESS FOR EVERY CONSUMER AND LOSSY IN THE ABSTRACT
 *
 * An existing row's exact first receipt is not recoverable from a `date`, so the
 * backfill is `day::timestamp` — midnight of the day the row already claimed.
 * **Every reader of this column truncates it to a day**, and
 * `date_trunc('day', day::timestamp) = day`, so no rollup and no sweep can tell
 * the difference. What differs is `WarehouseSnapshot`'s rendering, which is only
 * ever compared between two rebuilds — and a rebuild rewrites the row from L0.
 * ⛔ **This is the one place the *"L1 and L2 are derived and disposable"* rule is
 * being leaned on rather than merely cited**, and it is leaned on to avoid
 * deleting rows a migration was not asked to delete.
 *
 * ⚠️ **THE DEDUPE IS REQUIRED AND IT IMPLEMENTS 8041.** A table holding the split
 * this ruling abolishes cannot take the new key, so the earliest piece of a split
 * session wins and the later ones go — the day the session **started**, which is
 * exactly 8041's rule applied to the rows that already exist. Ordered on
 * `(day, ctid)` so it is total rather than merely usually total.
 *
 * ## ✅ AND FOUR SIGN CHECKS, BECAUSE THIS TABLE IS WHERE THAT AUDIT STARTED
 *
 * `unsignedInteger()` is documentation on PostgreSQL and reaches nothing;
 * `tests/Feature/Schema/UnsignedColumnConstraintsTest.php` exists to say so, and
 * the value that started it was **`l2_fact_session.active_s`** — a hostile
 * `active_ms: -100000` stored `-100` through the real collector route with
 * nothing raised, while five other hostile values raised loud `SQLSTATE` errors.
 * The derivation clamps it now (`greatest(…, 0)`); this is that invariant said
 * once in the database, where deleting the clamp reddens instead of storing a
 * plausible negative duration on a dashboard. ⚠️ **Eighteen further L2 columns
 * on four other marts have no such CHECK and are not touched here** — they are
 * reported as owed rather than swept up in a migration about this table's key.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ⚠️ THE SPLIT ROWS FIRST, OR THE NEW KEY CANNOT BE ADDED. `ctid` is the
        // tie-break of last resort: it is not stable across a rewrite, but this
        // statement and the key that follows it are one transaction.
        DB::statement(<<<'SQL'
            DELETE FROM l2_fact_session s
             WHERE s.ctid <> (
                 SELECT t.ctid
                   FROM l2_fact_session t
                  WHERE t.business_id = s.business_id
                    AND t.session_id  = s.session_id
                  ORDER BY t.day ASC, t.ctid ASC
                  LIMIT 1
             )
        SQL);

        DB::statement('ALTER TABLE l2_fact_session ADD COLUMN first_received_at timestamp(3) NULL');

        DB::statement('UPDATE l2_fact_session SET first_received_at = day::timestamp');

        DB::statement('ALTER TABLE l2_fact_session ALTER COLUMN first_received_at SET NOT NULL');

        // ⚠️ DROPPED EXPLICITLY. `DROP COLUMN day` would take the primary key
        // with it as a side effect and a `NOTICE`, which is the kind of implicit
        // schema change a reader of this file should not have to know about.
        DB::statement('ALTER TABLE l2_fact_session DROP CONSTRAINT l2_fact_session_pkey');
        DB::statement('ALTER TABLE l2_fact_session DROP COLUMN day');
        DB::statement('ALTER TABLE l2_fact_session ADD CONSTRAINT l2_fact_session_pkey PRIMARY KEY (business_id, session_id)');

        DB::statement(<<<'SQL'
            ALTER TABLE l2_fact_session
                ADD CONSTRAINT l2_fact_session_counts_are_not_negative
                CHECK (
                    duration_s      >= 0
                    AND active_s    >= 0
                    AND pageviews   >= 0
                    AND conversions >= 0
                )
        SQL);

    }

    /**
     * ⚠️ **THE DOWN IS HONEST ABOUT WHAT IT CANNOT RESTORE.** Rebuilding `day`
     * from `first_received_at` is exact — it is the derivation the old column
     * used — but the rows `up()` deleted are gone, and only a replay puts a
     * split back. That is the correct asymmetry: `up()` removes a state the
     * ruling says may not exist.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE l2_fact_session DROP CONSTRAINT l2_fact_session_counts_are_not_negative');

        DB::statement('ALTER TABLE l2_fact_session ADD COLUMN day date NULL');
        DB::statement("UPDATE l2_fact_session SET day = date_trunc('day', first_received_at)::date");
        DB::statement('ALTER TABLE l2_fact_session ALTER COLUMN day SET NOT NULL');

        DB::statement('ALTER TABLE l2_fact_session DROP CONSTRAINT l2_fact_session_pkey');
        DB::statement('ALTER TABLE l2_fact_session DROP COLUMN first_received_at');
        DB::statement('ALTER TABLE l2_fact_session ADD CONSTRAINT l2_fact_session_pkey PRIMARY KEY (business_id, day, session_id)');
    }
};

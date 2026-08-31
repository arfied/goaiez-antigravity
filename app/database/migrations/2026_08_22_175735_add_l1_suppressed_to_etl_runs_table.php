<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a replay derived and could not write — decisions 7924–7929.
 *
 * ⛔ **THE TRIPLE THIS COLUMN EXISTS TO EXPLAIN.** `App\Services\Warehouse\Replayer`
 * deletes `l1_events` over a `received_at` range and inserts through
 * `L1Loader::insert()`, which is `insertOrIgnore` against the primary key
 * `(business_id, event_id)`. A retried beacon received at 23:59:59 and again at
 * 00:00:02 therefore has one copy **outside** the deleted range: the in-range
 * insert conflicts with a row the delete never covered, and is ignored. Before
 * this column a single-day replay of the second day recorded `l0_lines = 1`,
 * `l1_rows = 0`, `l0_rejected = 0` — a triple no documented behaviour of that
 * class explains — while the event was absent from every mart of that day.
 *
 * ⚠️ **IT IS `l0_rejected`'s ARGUMENT, ONE LAYER DOWN, AND THAT TABLE ALREADY
 * MADE IT.** The creating migration: *"a silent zero and a silent thousand look
 * identical, and the version of this failure that actually happens is a client
 * shipping a broken payload to one tenant for a week."* A retried beacon that
 * straddles UTC midnight is not a broken client at all — it is the ordinary
 * behaviour of `navigator.sendBeacon` at every midnight — so the silent-thousand
 * case here is the *expected* one rather than the pathological one.
 *
 * ⚠️ **NOT A REJECT AND DELIBERATELY NOT FOLDED INTO `l0_rejected`.** A reject is
 * a line this warehouse could not read; a suppression is a line it read
 * perfectly and did not need, because the event is already stored on the day of
 * its first receipt. Adding them together would make an operator reading a
 * non-zero `l0_rejected` go looking for a malformed payload that does not exist.
 *
 * ⚠️ **`etl_runs` IS NOT PART OF THE BYTE-IDENTICAL COMPARISON**, so adding a
 * column here cannot move `WarehouseSnapshot::digest()`:
 * `App\Services\Warehouse\WarehouseSnapshot` enumerates the seven derived tables
 * and this is not one of them, and the reproducible-DDL lint excludes this table
 * by name for the same reason (its own creating migration says so about the
 * serial primary key).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etl_runs', function (Blueprint $table): void {
            // ⚠️ A DEFAULT OF ZERO RATHER THAN NULLABLE, AND IT IS HONEST FOR THE
            // ROWS THAT PREDATE IT. Every run written before this column existed
            // resolved its duplicates the same way — `insertOrIgnore` kept the
            // first copy it met — so the count it would have recorded is
            // unknowable, but a run over a range with no straddling duplicate is
            // the overwhelmingly common case and zero is what it recorded. A
            // nullable column would make every reader distinguish "none" from
            // "before we counted", which is a distinction no screen can use.
            $table->unsignedInteger('l1_suppressed')->default(0)->after('l1_rows');
        });

        // The compound CHECK gains a sixth conjunct rather than a second
        // constraint arriving beside it. `tests/Feature/Schema/UnsignedColumnConstraintsTest.php`
        // drives one case per *column* against this name, and two constraints on
        // one table for one property is a second thing to remember;
        // `Replayer::rebuildL1()` cannot produce a negative here — it is
        // `count($offered) - $written` and `$written` never exceeds the offered
        // rows — which is exactly the kind of "cannot happen" this table's other
        // five conjuncts already bound.
        DB::statement('ALTER TABLE etl_runs DROP CONSTRAINT etl_runs_counts_are_not_negative');

        DB::statement(<<<'SQL'
            ALTER TABLE etl_runs ADD CONSTRAINT etl_runs_counts_are_not_negative
                CHECK (l0_objects >= 0 AND l0_lines >= 0 AND l1_rows >= 0 AND l2_rows >= 0
                       AND l0_rejected >= 0 AND l1_suppressed >= 0)
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE etl_runs DROP CONSTRAINT etl_runs_counts_are_not_negative');

        DB::statement(<<<'SQL'
            ALTER TABLE etl_runs ADD CONSTRAINT etl_runs_counts_are_not_negative
                CHECK (l0_objects >= 0 AND l0_lines >= 0 AND l1_rows >= 0 AND l2_rows >= 0
                       AND l0_rejected >= 0)
        SQL);

        Schema::table('etl_runs', function (Blueprint $table): void {
            $table->dropColumn('l1_suppressed');
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where the wall clock is allowed to live.
 *
 * `GOAIEZ_PIXEL_MASTER_BUILD` §11 row 6 asks for `etl_runs` beside the L0→L1
 * consumer, and decision 4862 gives it the job that makes the replay gate
 * possible at all.
 *
 * ⛔ **THE PROBLEM THIS SOLVES, BECAUSE IT IS EASY TO READ THIS TABLE AS
 * BOOKKEEPING.** §5.3's L1 DDL carries an `ingested_at`, and an honest
 * implementation of it stamps every derived row with the moment the derivation
 * ran. That single column makes §11 row 7's *"truncate L1/L2, rebuild from L0,
 * byte-identical results"* **unachievable by construction** — the second run is
 * at a different moment, so every row differs, and the only ways out are to drop
 * the column or to exclude it from the comparison. Excluding it is worse than
 * dropping it: the test then passes while asserting something narrower than its
 * name (352, 397), and the next person reads a green "byte-identical" suite over
 * a table that is not.
 *
 * So the fact is not discarded, it is **moved to where it is true**. "When did
 * this row's data arrive" is receipt metadata and lives in L0, archived once.
 * "When did a rebuild run, over what range, and what did it produce" is a fact
 * about the *run* and lives here — one row per replay, never derived from L0,
 * never part of the byte comparison, and therefore free to carry every clock
 * reading it likes.
 *
 * ⚠️ **TENANT-OWNED, THOUGH A REPLAY FEELS LIKE AN OPERATION.** A run covers one
 * business's L0 prefix and its counts are that business's numbers; filing them
 * platform-wide would put one customer's volume in a table another customer's
 * support query reads. `staff_events` (741) is the shape for the platform-scoped
 * case and this is not it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etl_runs', function (Blueprint $table): void {
            // ⚠️ A SERIAL HERE IS CORRECT AND WOULD BE WRONG ONE TABLE OVER.
            // The reproducible-DDL lint deliberately does not cover this table:
            // it is not derived from L0 and is never rebuilt, so a sequence is
            // just an identifier. The lint's subject is `l1_*` and `l2_*`, which
            // is exactly the set that has to survive a truncate.
            $table->id();

            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();

            // The closed range replayed, as UTC dates — the same bounds the
            // command took.
            $table->date('from_day');
            $table->date('to_day');

            // What it did. `l0_objects` is the lineage number that answers "did
            // the archive have what we thought it had"; the row counts answer
            // "and did it produce what it produced last time".
            $table->unsignedInteger('l0_objects');
            $table->unsignedInteger('l0_lines');
            $table->unsignedInteger('l1_rows');
            $table->unsignedInteger('l2_rows');

            // ⛔ THE COUNT OF WHAT WAS DROPPED, AND IT IS NOT §11 ROW 6's
            // `ingest_rejects`. That table — "Rejects captured, never silent" —
            // is unbuilt (decision 4580 part 5), so what exists is a number
            // telling an operator that something was dropped and not which line
            // or why. It is here rather than nowhere because a silent zero and a
            // silent thousand look identical, and the version of this failure
            // that actually happens is a client shipping a broken payload to one
            // tenant for a week.
            $table->unsignedInteger('l0_rejected');

            // ⚠️ THE SNAPSHOT DIGEST, AND THE REASON THIS COLUMN IS THE POINT OF
            // THE TABLE IN PRODUCTION. The test suite proves byte-identity by
            // rebuilding twice inside one run; an operator cannot do that on
            // seven years of archive. What they can do is compare this digest
            // with the previous run's over the same range — so a derivation that
            // silently started producing different bytes is answerable after the
            // fact rather than only in CI.
            $table->string('snapshot_digest', 64);

            // Two clock readings, and this is the only table in the warehouse
            // permitted to have them.
            $table->timestamp('started_at', 3);
            $table->timestamp('finished_at', 3);

            $table->index(['business_id', 'from_day', 'to_day'], 'etl_runs_tenant_range_index');
        });

        DB::statement('ALTER TABLE etl_runs ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE etl_runs FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON etl_runs
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('etl_runs');
    }
};

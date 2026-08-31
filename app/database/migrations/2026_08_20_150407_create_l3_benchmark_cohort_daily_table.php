<?php

declare(strict_types=1);

use App\Enums\BenchmarkMetric;
use App\Enums\BenchmarkVertical;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L3 — the de-identified network layer. `GOAIEZ_PIXEL_MASTER_BUILD` §5.5's
 * `benchmark_cohort_daily`, and the layer `29` §2 rule 24's last sentence is
 * about: *"PHI never reaches L3."*
 *
 * ⛔ **UNTIL THIS MIGRATION THERE WAS NO L3, AND THAT IS WHY THREE TESTS IN THIS
 * SUITE ASSERTED A LAYER'S ABSENCE RATHER THAN ITS PROPERTIES** (4868, 4870).
 * `Architecture/WarehouseTest`'s *"no L3 object exists"* tripwire and
 * `Architecture/PixelTest`'s `^l[03]` sibling both carried a written debt to
 * whoever reddened them, and this table is what redeems it. Their replacements
 * are in `WarehouseTest`, and each of the three rules below is held by a
 * mechanism rather than by this docblock — which is the order `CLAUDE.md`
 * 314–316 insists on.
 *
 * ---------------------------------------------------------------------------
 * RULE 1 — NO `tenant_id`, NO RAW IDENTIFIER, BY SCHEMA
 * ---------------------------------------------------------------------------
 * §5.5: *"no `tenant_id`, no raw identifiers, no event rows, no free text
 * originating from tenant content. A CI test MUST assert that no L3 table
 * contains a column capable of holding tenant-identifying data."*
 *
 * ⛔ **THE OPERATIVE WORD IS "CAPABLE".** Leaving `business_id` out is the easy
 * half; the hard half is that a plain `text` column is *capable* of holding a
 * business name whatever anybody intended. So **this table has no unconstrained
 * text column at all**:
 *
 *  · `vertical` and `metric` are CHECK-constrained to the values of
 *    [[BenchmarkVertical]] and [[BenchmarkMetric]], built from the enums below
 *    so the two lists cannot be typed apart. The database refuses anything else
 *    — including a business name, a URL, a page path or a session id.
 *  · every other column is a `date` or an integer.
 *
 * ⚠️ **THE DIMENSIONS ARE COLUMNS RATHER THAN §5.5'S SINGLE `cohort_key`
 * STRING, AND THAT IS THE WHOLE MECHANISM.** A `cohort_key` of the form
 * `vertical|size_band|metro_band` is a `text` column by construction, and a
 * CHECK over its *contents* would have to enumerate the cross product or fall
 * back to a regex that any name matching `[a-z|]+` would satisfy. Splitting it
 * is what makes the constraint expressible. ClickHouse's `ORDER BY` needed one
 * key; Postgres has composite primary keys.
 *
 * ⚠️ **AND ONLY ONE OF §5.5'S THREE DIMENSIONS IS PRESENT** (decision 5941).
 * `businesses.size_band` and `businesses.metro_band` exist in the Stage 0 schema
 * and **nothing has ever written either of them** — 272's shape, the same as
 * `vertical` (which at least `BusinessFactory` writes). A dimension whose only
 * value is `null` cannot partition anything, and a dimension derived from
 * measured traffic would partition tenants by the very quantity `sessions`
 * benchmarks, which is circular. One dimension also means the *largest* cohorts,
 * which is the safe direction for the rule below. They arrive when they have
 * writers; adding a column then is a migration and a `PRIMARY KEY` change, and
 * the layer is derived and disposable, so it costs a rebuild and nothing else.
 *
 * ---------------------------------------------------------------------------
 * RULE 2 — k≥8 SUPPRESSION, AT THE DATABASE
 * ---------------------------------------------------------------------------
 * §5.5: `tenant_count UInt16, -- suppress output when < 8`. §11 row 21's
 * acceptance criterion is *"n=7 cohort suppressed"*, `29` §11.2 row 25's gate is
 * *"k≥8 suppression"*, and `28` §5.4 mirrors the same number for cross-tenant
 * learning — *"cohort ≥8, mirroring the existing benchmark rule"*. **One number,
 * one unit: contributing tenants** (decision 5942).
 *
 * ⛔ **THE CHECK BELOW IS THE MECHANISM AND `NetworkBenchmarks` IS THE POLICY.**
 * The derivation refuses to insert a cohort with fewer than k contributors;
 * this constraint means a row below the floor **cannot be stored at all**, by
 * any writer, including a hand-typed `INSERT` in a console. `CLAUDE.md`'s
 * recurring shape is a protection that lives only in the code path that was
 * meant to apply it.
 *
 * ⚠️ **THE FLOOR IS 8 AND THE REGISTRY MAY ONLY RAISE IT.**
 * `benchmarks.min_cohort` (§7.3's `benchmark_min_cohort`, seeded 8) is read by
 * the derivation, which **refuses to run** if an operator has set it below this
 * floor rather than quietly using the larger of the two. Two sources of truth
 * for a privacy floor, one of them a settings screen showing 5 while the code
 * used 8, is worse than either. `WarehouseTest` compares this constraint against
 * `NetworkBenchmarks::K_ANONYMITY_FLOOR`.
 *
 * ⚠️ **SUPPRESSION IS ABSENCE, NEVER A ROUNDED OR BLANKED ROW.** A row that says
 * *"this cohort exists and here is a fuzzed number"* still confirms the cohort
 * exists and still moves under differencing. There is no `suppressed` flag here
 * and no null percentile: below k, nothing is written.
 *
 * ---------------------------------------------------------------------------
 * RULE 3 — PHI EXCLUDED AT THE ETL
 * ---------------------------------------------------------------------------
 * §5.5: *"PHI-classified tenants are excluded from L3 entirely. Filter at the
 * ETL boundary, and assert it in tests."* That filter is
 * `NetworkBenchmarks::contributions()`, asking [[\App\Services\Warehouse\PhiExclusion]]
 * per business, and it is deliberately **not** left to the fact that a covered
 * entity has no L2 rows to read: that is an outer guard making the inner one
 * unfalsifiable (398), and the raise-then-purge order means the window where it
 * is false is exactly the window that matters.
 *
 * ---------------------------------------------------------------------------
 * WHAT THIS TABLE IS NOT
 * ---------------------------------------------------------------------------
 * ⛔ **NOT A SEPARATE DATABASE WITH ITS OWN CREDENTIALS**, which §5.5's heading
 * asks for. Decisions 88–89 collapsed the whole warehouse into Postgres and `29`
 * line 621 records the reconciled architecture as *"L1/L2/L3 in Postgres"*. A
 * second Postgres role for this one table was considered and refused: the ETL
 * that writes it *is* this application, so the writing role would have to be the
 * app's, and a read-only role with no reader is 272's shape. The protection that
 * survives the collapse is the one that does not depend on credentials — a table
 * that cannot hold an identifier.
 *
 * ⛔ **NOT §5.5's `identifier_graph`, AND THAT IS A REFUSAL RATHER THAN AN
 * OMISSION** (decision 5943). §7.4 asks for it *"built, disabled"* behind
 * `network_identity_graph_enabled`. `CLAUDE.md` records the state of that flag
 * in terms: *"`IDENTITY_RESOLUTION_ENABLED` does not exist — it is neither built
 * nor disabled"*, and calls a control described as present-and-off *"the most
 * dangerous form"* of a stale claim. A cross-tenant table of identifier hashes
 * is the one structure in this specification that would make L3 re-identifying;
 * building it now buys an option nobody has asked to exercise, at the cost of
 * the property this whole layer is for.
 *
 * ⛔ **AND NOT §5.5's `source_performance`** (decision 5944). It is the same
 * shape one dimension deeper — cohort × `source_key` — and `source_key` is
 * `l2_fact_session`'s, which is `'referral:' || referrer_host` for referral
 * traffic: **a string derived from a third party's hostname, arriving from a
 * visitor's browser**. Publishing it cross-tenant needs its own argument about
 * what a referrer host can reveal, and multiplying every cohort by it shrinks
 * every k. One mart with all three rules genuinely held is worth more than two
 * with one of them argued in a hurry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('l3_benchmark_cohort_daily', function (Blueprint $table): void {
            // ⛔ NO `id()`. A sequence is a value a rebuild cannot reproduce, and
            // `WarehouseTest`'s reproducible-DDL lint fails the build on one in
            // any derived table — which now includes this one.
            //
            // ⛔ AND NO `business_id`, WHICH IS THE POINT OF THE LAYER. Do not
            // add one "for debugging": the lint below asserts this table's
            // entire column list, and the RLS census in `TenancyTest` names this
            // table as an exception on the strength of it having no tenant to
            // scope by.
            $table->date('day');

            // The cohort. CHECK-constrained below.
            $table->string('vertical', 32);
            $table->string('metric', 32);

            // ⚠️ HOW MANY TENANTS THE DISTRIBUTION IS OVER, PER METRIC RATHER
            // THAN PER COHORT. A business with no sessions on a day contributes
            // to `sessions` and to none of the rates — it has no denominator —
            // so the two rows legitimately carry different counts, and a single
            // per-cohort count would overstate k for the rates.
            $table->unsignedSmallInteger('tenant_count');

            // §5.5's p25/p50/p75, as exact integers in the metric's own unit
            // (see BenchmarkMetric). Never `Float64`.
            $table->bigInteger('p25');
            $table->bigInteger('p50');
            $table->bigInteger('p75');

            $table->primary(['day', 'vertical', 'metric'], 'l3_benchmark_cohort_daily_pkey');
        });

        $verticals = collect(BenchmarkVertical::values())
            ->map(fn (string $value): string => "'".$value."'")
            ->implode(', ');

        $metrics = collect(BenchmarkMetric::values())
            ->map(fn (string $value): string => "'".$value."'")
            ->implode(', ');

        // ⛔ THE "NO FREE TEXT" HALF OF RULE 1, AT THE DATABASE. Two closed
        // vocabularies, both built from the enums rather than typed here.
        DB::statement(<<<SQL
            ALTER TABLE l3_benchmark_cohort_daily
                ADD CONSTRAINT l3_benchmark_cohort_daily_vertical_is_closed
                CHECK (vertical IN ({$verticals}))
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE l3_benchmark_cohort_daily
                ADD CONSTRAINT l3_benchmark_cohort_daily_metric_is_closed
                CHECK (metric IN ({$metrics}))
        SQL);

        // ⛔ RULE 2, AT THE DATABASE. 8 is written as a literal rather than
        // imported from `NetworkBenchmarks::K_ANONYMITY_FLOOR`, so this
        // migration keeps running on a fresh install whatever happens to that
        // class — and `WarehouseTest` compares the two on every run, because a
        // constant raised without a migration would leave the floor here at 8
        // and the two claims disagreeing.
        //
        // The citation, so nobody reads 8 as a taste: `GOAIEZ_PIXEL_MASTER_BUILD`
        // §5.5 and §7.3 (`benchmark_min_cohort 8`), `29` §11.2 rows 19 and 25,
        // `29` §12.1 *"k-anonymity: cohort of 7 suppressed"*, and `28` §5.4.
        DB::statement(<<<'SQL'
            ALTER TABLE l3_benchmark_cohort_daily
                ADD CONSTRAINT l3_benchmark_cohort_daily_k_anonymity_floor
                CHECK (tenant_count >= 8)
        SQL);

        // Postgres has no unsigned integer — see the 2026-08-18 audit migration.
        // Aggregate-fed and constrained anyway, on that migration's own reversal:
        // "unreachable today" is the wrong test for a CHECK.
        DB::statement(<<<'SQL'
            ALTER TABLE l3_benchmark_cohort_daily
                ADD CONSTRAINT l3_benchmark_cohort_daily_percentiles_are_non_negative
                CHECK (p25 >= 0 AND p50 >= 0 AND p75 >= 0)
        SQL);

        // A distribution that is not ordered is a derivation bug, and this is
        // where it stops rather than where it renders.
        DB::statement(<<<'SQL'
            ALTER TABLE l3_benchmark_cohort_daily
                ADD CONSTRAINT l3_benchmark_cohort_daily_percentiles_are_ordered
                CHECK (p25 <= p50 AND p50 <= p75)
        SQL);

        // ⛔ NO ROW-LEVEL SECURITY, AND IT IS THE ONE TABLE IN THIS WAREHOUSE
        // WHERE THAT IS CORRECT RATHER THAN AN OVERSIGHT. Every RLS policy in
        // this schema is `business_id = current_setting('app.business_id')`;
        // there is no `business_id` here to compare, and a policy over a table
        // with no tenant column could only be `USING (true)`, which is a policy
        // that reads as protection and is not.
        //
        // `TenancyTest`'s *"every table without row-level security is a named
        // exception"* census is what holds this: the table is listed there with
        // this argument, and a second L3 table added without an entry reddens
        // the build.
    }

    public function down(): void
    {
        Schema::dropIfExists('l3_benchmark_cohort_daily');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L2 — `GOAIEZ_PIXEL_MASTER_BUILD` §5.4's `fact_source_daily`, and only that one.
 *
 * §5.4 specifies four dimensions and five facts. **One of the five is built**
 * (decision 4864), and the rest are owed rather than stubbed: `dim_tenant` is an
 * SCD Type 2 table whose correctness is a slice of its own, and `fact_session`
 * and `fact_conversion` sit on §8's sessionization and attribution, which are
 * unwritten. A mart filled by a derivation nobody has specified is a number on a
 * screen that means whatever the last person to touch it assumed.
 *
 * ⚠️ **THIS ONE WAS CHOSEN FOR WHAT IT EXERCISES, NOT FOR WHAT IT REPORTS.** The
 * replay gate has to hold over an *aggregate*, and this is the smallest mart in
 * §5.4 that has all three of the hazards an aggregate brings:
 *
 *  1. **A `COUNT(DISTINCT …)`**, whose result must not depend on the order rows
 *     were fed to it.
 *  2. **A date dimension**, where a rollup computed in the server's local zone
 *     puts an event in a different day either side of a DST change — so the
 *     truncation is `AT TIME ZONE 'UTC'`, written out, every time.
 *  3. ⛔ **TEXT DIMENSIONS, WHICH ARE THE SUBTLE ONE.** `utm_source` and
 *     `utm_medium` are sorted when the snapshot is taken, and **Postgres sorts
 *     text by the database's collation** — `'_a'` and `'Ab'` order differently
 *     under `en_US.UTF-8` and under `C`. A replay on a database created with a
 *     different `LC_COLLATE`, or after a glibc upgrade changes one, would
 *     produce the same rows in a different order and fail a byte comparison for
 *     a reason that has nothing to do with the derivation. The snapshot pins
 *     `COLLATE "C"`; see `WarehouseSnapshot`.
 *
 * The same reproducibility rules as `l1_events` apply and are enforced by the
 * same lint: no serial, no clock default, no `double precision`, no `timestamps()`.
 *
 * ⚠️ **THE FACTS ARE ALL INTEGERS AND THAT IS THE RULE, NOT A COINCIDENCE.** A
 * rate belongs in the report that divides two of these, never in the mart: a
 * stored `double precision` ratio is both order-dependent to compute and
 * ini-dependent to print.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('l2_fact_source_daily', function (Blueprint $table): void {
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();

            // A calendar date in UTC, derived from `l1_events.received_at`.
            $table->date('day');

            // ⚠️ NOT NULLABLE, AND `'(none)'` RATHER THAN NULL IS DELIBERATE.
            // These are primary-key columns: Postgres treats NULLs as distinct
            // in a unique index, so a nullable dimension would let the same
            // (business, day, no-source) rollup be inserted twice and a replay
            // would double it. The sentinel is written by the derivation, which
            // is the one place it can be applied consistently.
            $table->string('utm_source', 128);
            $table->string('utm_medium', 128);

            // §5.4's facts. Integers only.
            $table->unsignedInteger('events');
            $table->unsignedInteger('sessions');
            $table->unsignedInteger('visitors');
            $table->unsignedInteger('bot_events');

            // §5.4: "All L2 tables exclude bot traffic from tenant-facing
            // metrics (bot counts are reported separately)." `events`,
            // `sessions` and `visitors` are therefore human-only and
            // `bot_events` is the separate report — not a subset of `events`.
            $table->primary(['business_id', 'day', 'utm_source', 'utm_medium'], 'l2_fact_source_daily_pkey');
        });

        DB::statement('ALTER TABLE l2_fact_source_daily ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE l2_fact_source_daily FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON l2_fact_source_daily
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('l2_fact_source_daily');
    }
};

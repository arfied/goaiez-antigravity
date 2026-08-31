<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L2 — the real-user speed mart. `28` §4.3: *"the pixel collects `web_vitals`
 * events (LCP, CLS, INP, TTFB; p75 by device class) into the existing L1
 * pipeline. This is the baseline and the judge — lab tests are advisory only."*
 *
 * ---------------------------------------------------------------------------
 * ⛔ THIS TABLE STORES NO PERCENTILE, AND THAT IS THE WHOLE DESIGN
 * ---------------------------------------------------------------------------
 * It is a **daily histogram**: one row per (business, day, metric, device
 * class, bucket), carrying how many measurements landed in that bucket. The
 * p75 is computed by [[\App\Services\Warehouse\SiteVitals]] at read time.
 *
 * Two reasons, and the second is the one that would have bitten later.
 *
 *  1. ⛔ **A PERCENTILE IS WHERE REPLAY DETERMINISM GETS QUIETLY LOST.** §11
 *     row 7's gate is *byte-identical* rebuild, and `WarehouseTest`'s
 *     reproducible-DDL lint already refuses `double precision` and `real` in
 *     any derived table because floating-point addition is not associative. A
 *     stored percentile brings the same hazard by a different door:
 *     `percentile_cont` **interpolates**, returns `double precision` for float
 *     input, and renders through `extra_float_digits`; and any construction
 *     that has to break a tie between two equal values needs an ordering rule,
 *     which is where `LC_COLLATE` and `serialize_precision` get in. **A
 *     histogram has none of those** — it is `count(*)` over integer buckets,
 *     and the only arithmetic is integer division. There is nothing in this
 *     table a rebuild could render differently, so the gate holds over it by
 *     construction rather than by care.
 *  2. ⛔ **A DAILY p75 CANNOT BE ROLLED UP INTO A WEEKLY ONE, AND `28` §4.3
 *     ASKS FOR EXACTLY THAT.** Its triggers are *"p75 LCP or INP worsens >10%
 *     over 7 days vs the 14-day pre-change baseline"*. The mean of seven daily
 *     p75s is not the p75 of the seven days, and no amount of care makes it
 *     one. A mart of daily p75s would therefore force whoever builds the
 *     decider either to publish a statistic that is quietly wrong or to go back
 *     to `l1_events` — at which point the mart is decoration. Summing histogram
 *     buckets across days is exact to the bucket width.
 *
 * ⚠️ **THE BUCKET IS AN UPPER BOUND, NOT A FLOOR.** `bucket` is the smallest
 * multiple of the metric's width at or above the measurement, so the p75 the
 * reader reports is never *faster* than the truth — `28` §4.3's "never a
 * fabricated win" applied to a rounding rule. [[\App\Enums\WebVital]] holds the
 * widths, the units and the reasoning for each.
 *
 * ⚠️ **NO FLOAT AND NO `jsonb` COLUMN**, which is `WarehouseTest`'s lint rather
 * than a preference. `bucket` is an integer in the metric's smallest unit —
 * milliseconds for LCP/INP/TTFB, thousandths for CLS — which is the same rule
 * `CanonicalJson`'s docblock states for telemetry and CLAUDE.md states for
 * money.
 *
 * ⚠️ **`bucket` IS A SIGNED `bigInteger` AND THE VALUES IN IT ARE NEVER
 * NEGATIVE.** W23's lesson, kept rather than restated: `unsignedInteger()` in
 * this schema is a plain `integer` and refuses nothing, so a column named
 * "unsigned" would be a claim the database does not back. The non-negativity is
 * enforced where it is actually decided — the derivation drops a negative
 * measurement rather than clamping it — and by the CHECK below, which is the
 * one thing here that genuinely refuses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('l2_fact_vital_daily', function (Blueprint $table): void {
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();

            $table->date('day');

            // ⚠️ A STRING CAST TO `App\Enums\WebVital`, NEVER A DATABASE ENUM —
            // CLAUDE.md's rule, and its stated reason applies literally here: a
            // Postgres enum value cannot be dropped or reordered once added,
            // and this vocabulary is read off `resources/js/pixel.js`, which
            // moves.
            $table->string('metric', 16);

            // Likewise `App\Enums\DeviceClass`.
            $table->string('device_type', 32);

            $table->bigInteger('bucket');

            $table->unsignedInteger('samples');

            $table->primary(
                ['business_id', 'day', 'metric', 'device_type', 'bucket'],
                'l2_fact_vital_daily_pkey',
            );
        });

        // ⛔ THE ONE CONSTRAINT THAT ACTUALLY REFUSES SOMETHING. A negative
        // bucket would be a measurement filed as *faster than instant*, which
        // drags a p75 down and reads as a genuinely quick site. The derivation
        // is what drops negatives; this is what makes that a property of the
        // table rather than a property of one query nobody re-reads.
        DB::statement(<<<'SQL'
            ALTER TABLE l2_fact_vital_daily
                ADD CONSTRAINT l2_fact_vital_daily_bucket_is_not_negative
                CHECK (bucket >= 0)
        SQL);

        DB::statement('ALTER TABLE l2_fact_vital_daily ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE l2_fact_vital_daily FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON l2_fact_vital_daily
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('l2_fact_vital_daily');
    }
};

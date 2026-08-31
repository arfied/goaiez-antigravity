<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The three measurement columns `DATA-MODEL.md` §5.11 specified and slice A
 * deliberately did not create — `BUILD-PLAN` §2.11.3 slice H, decision 5525.
 *
 * ⛔ **THEY ARRIVE HERE BECAUSE THIS IS THE MIGRATION THAT GIVES THEM A
 * WRITER.** 5525's whole argument: `CLAUDE.md`'s most-repeated failure is a
 * column with no writer, whose tell is that an isolation test passes perfectly
 * against something nothing fills in. `App\Services\Actuation\SiteMeasurements`
 * is that writer, and it lands in the same branch.
 *
 * ⛔ **ALL THREE OR NONE, ON `site_changes_rollback_is_all_or_nothing`'s OWN
 * PRECEDENT (5524, and `subscriptions`' agreed price at 3533).** A row carrying
 * `measured_at` and no metrics is a claim that this change was judged, with
 * nothing behind it — and the verdict column beside it would then be a finding
 * whose evidence cannot be produced. `29` §2 rule 32's second half is *measure
 * 14–30 days, auto-rollback on regression*; a measurement nobody can inspect is
 * not a measurement.
 *
 * ⚠️ **BOTH METRIC COLUMNS ARE `jsonb` AND NEITHER IS A SCHEMA.** What can be
 * measured differs per change: a location with a pixel and no Search Console
 * grant has page traffic and no search data, and the reverse is ordinary too.
 * A column per metric would be a column that is null for most rows and a
 * migration for every metric the decider learns to read.
 * `App\Services\Actuation\ChangeMetrics` is the shape, and it is versioned
 * inside the document so a later reader can tell what it is looking at.
 *
 * ⚠️ **NO PAGE CONTENT EVER GOES IN HERE.** These are counts and window dates.
 * The snapshots on the same row already hold a verbatim copy of part of a
 * stranger's page and are the one place that is true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_changes', function (Blueprint $table): void {
            // What the fourteen days before the change looked like, read at
            // measurement time rather than at publish time — the marts are
            // rebuilt from L0 and a figure captured early would be a second,
            // un-replayable copy of a derived number (§5.1).
            $table->jsonb('baseline_metrics')->nullable();

            // The same figures over days 14–30 after the adapter reported
            // writing.
            $table->jsonb('measured_metrics')->nullable();

            // ⚠️ **WHEN THE QUESTION WAS ANSWERED, NOT WHEN THE WINDOW
            // CLOSED.** The windows are anchored on `applied_at` and are
            // written into the documents above, so this says when we looked —
            // which is what makes a deferred measurement (a Search Console
            // window Google has not finished counting) visible as a gap rather
            // than as a silently different window.
            $table->timestamp('measured_at')->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE site_changes
                ADD CONSTRAINT site_changes_measurement_is_all_or_nothing CHECK (
                    (measured_at IS NULL AND baseline_metrics IS NULL AND measured_metrics IS NULL)
                    OR (measured_at IS NOT NULL AND baseline_metrics IS NOT NULL AND measured_metrics IS NOT NULL)
                )
        SQL);

        // The sweep's own predicate: applied, not yet measured, not rolled
        // back. `business_id` leads because every read of this table is a
        // tenant's, and a btree index serves a leftmost prefix.
        DB::statement(<<<'SQL'
            CREATE INDEX site_changes_awaiting_measurement
                ON site_changes (business_id, applied_at)
                WHERE measured_at IS NULL AND applied_at IS NOT NULL AND rolled_back_at IS NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS site_changes_awaiting_measurement');

        DB::statement('ALTER TABLE site_changes DROP CONSTRAINT IF EXISTS site_changes_measurement_is_all_or_nothing');

        Schema::table('site_changes', function (Blueprint $table): void {
            $table->dropColumn(['baseline_metrics', 'measured_metrics', 'measured_at']);
        });
    }
};

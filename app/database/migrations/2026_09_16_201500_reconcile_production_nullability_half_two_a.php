<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * SITE-232: Reconcile production nullability, half two (a)
 *
 * Audit on 2026-09-16 compared production to a fresh migrate. Production had
 * 13 columns that were nullable which the tree declares as NOT NULL.
 *
 * The owner ruled: production matches the tree (half two). This migration
 * applies SET NOT NULL to those 13 columns. Because SET NOT NULL aborts
 * on production if any row has a null, this migration is self-guarding:
 * it backfills the tree's own default before making the column NOT NULL.
 * For columns without a tree default, it warns and skips if a null exists.
 *
 * `gbp_connections.location_id` bigint-vs-varchar / foreign-key is NOT
 * touched here, pending product decision.
 */
return new class extends Migration
{
    /** column => the tree's own declared default, used ONLY to backfill nulls before SET NOT NULL */
    private const SET_NOT_NULL_WITH_DEFAULT = [
        'ai_calls' => ['cost_cents' => 0, 'latency_ms' => 0, 'prompt_version' => 1, 'tokens_in' => 0, 'tokens_out' => 0, 'ttft_ms' => 0, 'usage_unavailable' => false],
        'brand_registrations' => ['brand_type' => 'shared', 'registration_status' => 'pending'],
        'gbp_connections' => ['profile_status' => 'active'],
        'operator_alerts' => ['severity' => 'warning', 'status' => 'open'],
        'voicemails' => ['duration_seconds' => 0],
    ];

    /** no default in the tree: only SET NOT NULL when no null exists, otherwise skip and warn */
    private const SET_NOT_NULL_NO_DEFAULT = [
        'carrier_credentials' => ['business_id'],
    ];

    public function up(): void
    {
        foreach (self::SET_NOT_NULL_WITH_DEFAULT as $t => $columns) {
            foreach ($columns as $c => $default) {
                if (! Schema::hasTable($t) || ! Schema::hasColumn($t, $c)) continue;

                $nullable = DB::selectOne("SELECT is_nullable FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?", [$t, $c]);
                if ($nullable && $nullable->is_nullable === 'NO') {
                    continue;
                }

                DB::table($t)->whereNull($c)->update([$c => $default]);
                DB::statement(sprintf('ALTER TABLE "%s" ALTER COLUMN "%s" SET NOT NULL', $t, $c));
            }
        }

        foreach (self::SET_NOT_NULL_NO_DEFAULT as $t => $columns) {
            foreach ($columns as $c) {
                if (! Schema::hasTable($t) || ! Schema::hasColumn($t, $c)) continue;

                $nullable = DB::selectOne("SELECT is_nullable FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?", [$t, $c]);
                if ($nullable && $nullable->is_nullable === 'NO') {
                    continue;
                }

                if (DB::table($t)->whereNull($c)->exists()) {
                    Log::warning("reconcile half two: {$t}.{$c} keeps NULLs; SET NOT NULL skipped");
                    continue;
                }

                DB::statement(sprintf('ALTER TABLE "%s" ALTER COLUMN "%s" SET NOT NULL', $t, $c));
            }
        }
    }

    public function down(): void
    {
        // Dropping the constraint again is half one's territory and would be
        // a regression by ruling. This down method is a deliberate no-op.
    }
};

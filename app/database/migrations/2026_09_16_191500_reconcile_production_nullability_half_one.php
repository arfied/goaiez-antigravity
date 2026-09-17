<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SITE-231: Reconcile production nullability (half one)
 *
 * Audit on 2026-09-16 compared production to a fresh migrate. Production had
 * 18 columns that were NOT NULL which the migrations create as nullable.
 * Production also lacked `qa_settings.ticket_recipient_id`.
 *
 * The owner ruled: production matches the tree (half one). This migration
 * drops those 18 NOT NULL constraints (a no-op on a database built from migrations)
 * and adds the missing column to `qa_settings`.
 *
 * Half two (SET NOT NULL on 13 columns the tree requires and production does not;
 * the `gbp_connections.location_id` bigint-vs-varchar design) is deliberately excluded.
 */
return new class extends Migration
{
    private const DROP_NOT_NULL = [
        'ai_calls' => ['model', 'provider', 'task'],
        'brand_registrations' => ['provider', 'status', 'submitted_at', 'submitted_by'],
        'gbp_connections' => ['location_id'],
        'knowledge_chunks' => ['content', 'source_id'],
        'operator_alerts' => ['fired_at', 'kind', 'summary'],
        'short_links' => ['purpose', 'target_url', 'token'],
        'voicemails' => ['call_id'],
    ];

    public function up(): void
    {
        foreach (self::DROP_NOT_NULL as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    DB::statement(sprintf('ALTER TABLE "%s" ALTER COLUMN "%s" DROP NOT NULL', $table, $column));
                }
            }
        }

        if (Schema::hasTable('qa_settings') && ! Schema::hasColumn('qa_settings', 'ticket_recipient_id')) {
            Schema::table('qa_settings', fn (Blueprint $t) => $t->unsignedBigInteger('ticket_recipient_id')->nullable());
        }
    }

    public function down(): void
    {
        // Re-adding NOT NULL fails on any production row that is null there,
        // and is half two's decision. This down method is a deliberate no-op.
    }
};

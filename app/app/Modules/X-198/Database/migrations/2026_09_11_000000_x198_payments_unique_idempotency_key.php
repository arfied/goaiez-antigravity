<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GatewayEngine::capture()'s pre-check is check-then-act across a live HTTP call.
 * This partial index is what makes the second writer lose, and the catch arm in
 * GatewayEngine::capture() is what turns losing into returning the winner's row.
 * The two are one change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        DB::statement(
            "CREATE UNIQUE INDEX payments_business_idempotency_active_unique
             ON payments (business_id, idempotency_key)
             WHERE status <> 'failed'"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS payments_business_idempotency_active_unique');
    }
};

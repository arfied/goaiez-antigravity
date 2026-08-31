<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array PLATFORM_EXEMPTIONS = [
        'jobs',
        'failed_jobs',
        'job_batches',
        'opt_outs',
        'suppression_lifts',
        'tenant_deletion_requests',
        'support_queue_entries',
        'data_requests',
        'gbp_account_bindings',
        'gbp_grant_revocation_attempts',
        'gbp_profile_bindings',
        'places_api_calls',
        'voice_usage_events',
        'zernio_account_days',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::PLATFORM_EXEMPTIONS as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }
    }

    public function down(): void
    {
        // Platform-scoped tables remain exempt
    }
};

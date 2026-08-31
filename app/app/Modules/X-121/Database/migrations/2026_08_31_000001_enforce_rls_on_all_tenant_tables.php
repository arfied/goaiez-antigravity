<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Platform-scoped and system tables that must remain accessible cross-tenant
     * (e.g. platform-wide opt-outs where business_id is NULL for platform STOPs,
     * asynchronous worker queues, and global administrative triage).
     */
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
        $tables = DB::select(<<<'SQL'
            SELECT c.relname AS tablename
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            JOIN information_schema.columns col ON col.table_name = c.relname AND col.table_schema = 'public'
            WHERE n.nspname = 'public'
              AND c.relkind = 'r'
              AND col.column_name = 'business_id'
        SQL);

        foreach ($tables as $t) {
            $tableName = $t->tablename;
            if (in_array($tableName, self::PLATFORM_EXEMPTIONS, true)) {
                continue;
            }

            DB::statement("ALTER TABLE {$tableName} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$tableName} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tableName}");
            DB::statement(<<<SQL
                CREATE POLICY tenant_isolation ON {$tableName}
                    USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                    WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
            SQL);
        }
    }

    public function down(): void {}
};

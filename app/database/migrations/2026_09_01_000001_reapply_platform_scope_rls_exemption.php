<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-apply the platform-scope RLS exemption that never took effect.
 *
 * `app/Modules/X-121/Database/migrations/2026_08_31_000006_exempt_platform_scoped_tables_from_tenant_rls.php`
 * is recorded as ran (migrations id 400, batch 3), but on 2026-09-01 every table
 * it names except the three framework queue tables still carried
 * `relrowsecurity = true`, `relforcerowsecurity = true` and a `tenant_isolation`
 * policy. The repo-root `error_log` shows why: the statements were executed as
 * the runtime role `goaiez_app` on the `pgsql` connection and died on
 * `must be owner of table opt_outs`. `ALTER TABLE` needs ownership, and every
 * table belongs to `goaiez_owner`.
 *
 * The consequence is not theoretical. `ConsentService` records carrier STOPs
 * with `business_id = null` (the register is platform-wide by design — see the
 * docblock on `create_opt_outs_table`), and a NULL tenant fails the policy's
 * `WITH CHECK`, so every STOP, data request and deletion request raises 42501.
 *
 * This migration carries the same list and the same three statements, and is
 * idempotent: `DROP POLICY IF EXISTS`, `NO FORCE` and `DISABLE` are all no-ops
 * on a table already in the intended state. It must run under the owner role,
 * which `php artisan migrate` does automatically
 * (`AppServiceProvider::routeSchemaCommandsToTheOwnerRole()`).
 */
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
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }

    public function down(): void
    {
        // Platform-scoped tables remain exempt; re-enabling tenant RLS on the
        // STOP register would reintroduce the defect this migration repairs.
    }
};

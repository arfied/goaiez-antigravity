<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `operator_alerts` is a platform table and must not carry tenant RLS.
 *
 * `2026_08_15_075703_create_operator_alerts_table.php` says so in its own
 * heading — "No tenant, and therefore no RLS" — for the reason
 * `platform_halt_incidents` settled: a nullable `business_id` forces a policy
 * admitting NULL, and a policy admitting NULL admits every row.
 *
 * Two later module migrations contradicted it without saying so:
 * `X-121/…000007_reconcile_legacy_module_columns.php` added a nullable
 * `business_id` column, and `X-121/…000001_enforce_rls_on_all_tenant_tables.php`
 * then secured every table carrying that column with `tenant_isolation`
 * (`business_id = current_setting('app.business_id')`), FORCEd. The exemption
 * list in `…000006` never included this table.
 *
 * The effect surfaced the minute the platform schedule was registered on
 * 2026-09-01: `ops:watch-platform-health` found `anthropic_api_key` unusable
 * and `OperatorAlerts::raise()` failed with a QueryException — the alert row
 * has no tenant, NULL fails `WITH CHECK`, and the one channel built to report
 * platform faults could not record one. Same three idempotent statements as
 * `…000001_reapply_platform_scope_rls_exemption`; runs under the owner role via
 * `php artisan migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('operator_alerts')) {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON operator_alerts');
        DB::statement('ALTER TABLE operator_alerts NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE operator_alerts DISABLE ROW LEVEL SECURITY');
    }

    public function down(): void
    {
        // The table is platform-scoped by design; there is no tenant policy to
        // restore.
    }
};

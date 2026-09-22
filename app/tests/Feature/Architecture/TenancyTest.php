<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class TenancyTest extends TestCase
{
    /**
     * Platform-scoped and system tables that must remain exempt from tenant-level RLS.
     *
     * @var array<string, string>
     */
    public static array $exempt = [
        'opt_outs' => 'Platform-wide opt-outs where business_id is NULL for platform STOP commands (Lane A compliance).',
        'suppression_lifts' => 'Platform-wide suppression lift tokens and cross-tenant consent resets.',
        'tenant_deletion_requests' => 'Global tenant deletion queue and erasure processing requests.',
        'support_queue_entries' => 'Cross-tenant operator support queue entries and escalation tickets.',
        'data_requests' => 'Global GDPR/CCPA data export and erasure orchestration requests.',
        'gbp_account_bindings' => 'Platform-level Google Business Profile OAuth grants and account bindings.',
        'gbp_grant_revocation_attempts' => 'Platform-wide audit log for GBP OAuth grant revocations.',
        'gbp_profile_bindings' => 'Global Google Business Profile location ID bindings and verification states.',
        'places_api_calls' => 'Platform Places API cache and billable consumption tracking across tenants.',
        'voice_usage_events' => 'Global telecom carrier usage events and un-tenanted inbound call logs.',
        'zernio_account_days' => 'Platform-level Zernio aggregation account daily quotas and rate limiters.',
        'operator_alerts' => 'Deliberately exempt via 2026_09_01_000002_exempt_operator_alerts_from_tenant_rls.php because a nullable business_id makes tenant_isolation WITH CHECK reject rows.',
    ];

    public function test_all_rls_exempt_tables_have_documented_reasons(): void
    {
        $this->assertNotEmpty(self::$exempt);

        foreach (self::$exempt as $table => $reason) {
            $this->assertNotEmpty($reason, "Table {$table} must have a documented exemption reason.");
        }
    }

    public function test_exempt_list_contains_all_twelve_platform_scoped_tables(): void
    {
        $requiredTables = [
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
            'operator_alerts',
        ];

        foreach ($requiredTables as $table) {
            $this->assertArrayHasKey($table, self::$exempt, "Exempt list must document {$table}");
        }
    }

    public function test_all_unexempted_tables_are_forced(): void
    {
        $rows = DB::select(<<<'SQL'
select c.relname as t, c.relrowsecurity as r, c.relforcerowsecurity as f
from pg_class c
join pg_namespace n on n.oid = c.relnamespace
join information_schema.columns col
  on col.table_name = c.relname and col.table_schema = 'public'
where n.nspname = 'public' and c.relkind = 'r' and col.column_name = 'business_id'
SQL
        );

        $examinedCount = 0;
        foreach ($rows as $row) {
            if (! array_key_exists($row->t, self::$exempt)) {
                $examinedCount++;
                $this->assertTrue(
                    $row->r && $row->f,
                    "Table {$row->t} is not exempt but lacks forced RLS. It needs enable+force row level security and a tenant_isolation policy, or a documented \$exempt entry if it is genuinely platform-scoped."
                );
            }
        }

        $this->assertGreaterThan(0, $examinedCount, 'No unexempt tables were examined. An empty result means the query is broken rather than that the schema is clean.');
    }

    public function test_all_exempt_tables_are_unforced(): void
    {
        $rows = DB::select(<<<'SQL'
select c.relname as t, c.relrowsecurity as r, c.relforcerowsecurity as f
from pg_class c
join pg_namespace n on n.oid = c.relnamespace
join information_schema.columns col
  on col.table_name = c.relname and col.table_schema = 'public'
where n.nspname = 'public' and c.relkind = 'r' and col.column_name = 'business_id'
SQL
        );

        $examinedCount = 0;
        foreach ($rows as $row) {
            if (array_key_exists($row->t, self::$exempt)) {
                $examinedCount++;
                $this->assertFalse(
                    $row->r && $row->f,
                    "Table {$row->t} is exempt but has forced RLS. It should leave \$exempt."
                );
            }
        }

        $this->assertGreaterThan(0, $examinedCount, 'No exempt tables were examined. An empty result means the query is broken rather than that the schema is clean.');
    }
}

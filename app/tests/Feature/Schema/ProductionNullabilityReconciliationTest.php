<?php

declare(strict_types=1);

namespace Tests\Feature\Schema;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class ProductionNullabilityReconciliationTest extends TestCase
{
    use RefreshesTenantDatabase;

    private const DROP_NOT_NULL = [
        'ai_calls' => ['model', 'provider', 'task'],
        'brand_registrations' => ['provider', 'status', 'submitted_at', 'submitted_by'],
        'knowledge_chunks' => ['content', 'source_id'],
        'operator_alerts' => ['fired_at', 'kind', 'summary'],
        'short_links' => ['purpose', 'target_url', 'token'],
        'voicemails' => ['call_id'],
    ];

    public function test_the_eighteen_reconciled_columns_are_nullable(): void
    {
        foreach (self::DROP_NOT_NULL as $table => $columns) {
            foreach ($columns as $column) {
                $row = DB::selectOne(
                    'select is_nullable from information_schema.columns where table_schema = current_schema() and table_name = ? and column_name = ?',
                    [$table, $column]
                );

                $this->assertNotNull($row, "Column {$table}.{$column} does not exist");
                $this->assertSame('YES', $row->is_nullable, "Column {$table}.{$column} is not nullable");
            }
        }
    }

    public function test_qa_settings_has_ticket_recipient_id(): void
    {
        $this->assertTrue(
            Schema::hasColumn('qa_settings', 'ticket_recipient_id'),
            'qa_settings is missing ticket_recipient_id'
        );
    }

    public function test_the_thirteen_tree_required_columns_are_not_nullable(): void
    {
        $pairs = [
            'ai_calls' => ['cost_cents', 'latency_ms', 'prompt_version', 'tokens_in', 'tokens_out', 'ttft_ms', 'usage_unavailable'],
            'brand_registrations' => ['brand_type', 'registration_status'],
            'carrier_credentials' => ['business_id'],
            'gbp_connections' => ['profile_status'],
            'operator_alerts' => ['severity', 'status'],
            'voicemails' => ['duration_seconds'],
        ];

        foreach ($pairs as $table => $columns) {
            foreach ($columns as $column) {
                $row = DB::selectOne(
                    'select is_nullable from information_schema.columns where table_schema = current_schema() and table_name = ? and column_name = ?',
                    [$table, $column]
                );

                $this->assertNotNull($row, "Column {$table}.{$column} does not exist");
                $this->assertSame('NO', $row->is_nullable, "Column {$table}.{$column} is nullable");
            }
        }
    }

    public function test_gbp_connections_location_id_is_a_bigint_foreign_key(): void
    {
        $col = DB::selectOne(
            'select data_type, is_nullable from information_schema.columns where table_schema = current_schema() and table_name = ? and column_name = ?',
            ['gbp_connections', 'location_id']
        );
        $this->assertNotNull($col);
        $this->assertSame('bigint', $col->data_type);
        $this->assertSame('NO', $col->is_nullable);

        $constraint = DB::selectOne(
            'select constraint_name from information_schema.table_constraints where table_schema = current_schema() and table_name = ? and constraint_type = ? and constraint_name = ?',
            ['gbp_connections', 'FOREIGN KEY', 'gbp_connections_location_id_foreign']
        );
        $this->assertNotNull($constraint);
        $this->assertSame('gbp_connections_location_id_foreign', $constraint->constraint_name);
    }
}

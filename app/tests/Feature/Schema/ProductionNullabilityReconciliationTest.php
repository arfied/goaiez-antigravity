<?php

declare(strict_types=1);

namespace Tests\Feature\Schema;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductionNullabilityReconciliationTest extends TestCase
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

    public function test_the_eighteen_reconciled_columns_are_nullable(): void
    {
        foreach (self::DROP_NOT_NULL as $table => $columns) {
            foreach ($columns as $column) {
                $row = DB::selectOne(
                    "select is_nullable from information_schema.columns where table_schema = current_schema() and table_name = ? and column_name = ?",
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
}

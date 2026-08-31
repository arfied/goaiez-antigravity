<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restore_tests')) {
            Schema::create('restore_tests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('backup_id')->index();
                $table->string('expected_checksum'); // TEST ANCHOR
                $table->string('actual_checksum');
                $table->unsignedInteger('expected_row_count');
                $table->unsignedInteger('restored_row_count');
                $table->string('status')->default('passed'); // passed, failed_checksum_mismatch, failed_row_count_mismatch
                $table->text('failure_reason')->nullable();
                $table->timestamp('tested_at')->useCurrent();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('runbooks')) {
            Schema::create('runbooks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('title');
                $table->string('trigger_event'); // G21-03
                $table->jsonb('steps');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('runbook_runs')) {
            Schema::create('runbook_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('runbook_id')->constrained('runbooks')->cascadeOnDelete();
                $table->string('status')->default('executing'); // executing, completed, failed
                $table->jsonb('executed_steps');
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('completed_at')->nullable();
            });
        }

        $tables = ['restore_tests', 'runbooks', 'runbook_runs'];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'business_id')) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
                DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");

                DB::statement(<<<SQL
                    CREATE POLICY tenant_isolation ON {$table}
                        USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                        WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                SQL);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('runbook_runs');
        Schema::dropIfExists('runbooks');
        Schema::dropIfExists('restore_tests');
    }
};

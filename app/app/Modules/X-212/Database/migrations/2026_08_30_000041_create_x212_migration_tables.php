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
        if (! Schema::hasTable('migration_runs')) {
            Schema::create('migration_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('source_system'); // service_titan, jobber, housecall_pro, csv
                $table->string('status')->default('started'); // started, dry_run_ready, committed, rolled_back
                $table->unsignedInteger('total_records')->default(0);
                $table->unsignedInteger('imported_records')->default(0);
                $table->unsignedInteger('rejected_records')->default(0);
                $table->boolean('is_silent_mode')->default(true); // Migration of 500 jobs sends nothing
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('migration_field_maps')) {
            Schema::create('migration_field_maps', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('migration_run_id')->constrained('migration_runs')->cascadeOnDelete();
                $table->string('source_field');
                $table->string('target_entity'); // person, job, invoice
                $table->string('target_field');
                $table->string('transform_rule')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('migration_rejects')) {
            Schema::create('migration_rejects', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('migration_run_id')->constrained('migration_runs')->cascadeOnDelete();
                $table->unsignedInteger('record_index');
                $table->jsonb('raw_data');
                $table->string('rejection_reason');
                $table->timestamps();
            });
        }

        $tables = ['migration_runs', 'migration_field_maps', 'migration_rejects'];

        foreach ($tables as $table) {
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

    public function down(): void
    {
        Schema::dropIfExists('migration_rejects');
        Schema::dropIfExists('migration_field_maps');
        Schema::dropIfExists('migration_runs');
    }
};

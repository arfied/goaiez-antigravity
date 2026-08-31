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
        if (! Schema::hasTable('routing_rules')) {
            Schema::create('routing_rules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('name');
                $table->string('rule_type')->default('polygon_territory'); // polygon_territory, returning_caller, workload_balanced
                $table->unsignedInteger('priority')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('territories')) {
            Schema::create('territories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('name');
                $table->unsignedBigInteger('assigned_staff_id')->nullable();
                $table->jsonb('polygon_geojson')->nullable();
                $table->jsonb('zip_codes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('assignments')) {
            Schema::create('assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('lead_id')->index();
                $table->unsignedBigInteger('assigned_staff_id')->index();
                $table->string('assignment_reason')->default('polygon_match'); // polygon_match, returning_caller, workload_balanced, sla_reassigned
                $table->string('status')->default('active'); // active, reassigned
                $table->timestamps();
            });
        }

        $tables = ['routing_rules', 'territories', 'assignments'];

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
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('territories');
        Schema::dropIfExists('routing_rules');
    }
};

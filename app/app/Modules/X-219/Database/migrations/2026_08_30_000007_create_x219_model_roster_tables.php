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
        if (! Schema::hasTable('ai_providers')) {
            Schema::create('ai_providers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('provider_name');
                $table->string('base_url')->nullable();
                $table->string('status')->default('healthy');
                $table->unsignedInteger('latency_p95_ms')->default(300);
                $table->unsignedInteger('error_rate_pct')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_models')) {
            Schema::create('ai_models', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('provider_id')->constrained('ai_providers')->cascadeOnDelete();
                $table->string('model_name');
                $table->unsignedInteger('context_window')->default(128000);
                $table->bigInteger('cost_per_1k_input_cents')->default(1);
                $table->bigInteger('cost_per_1k_output_cents')->default(2);
                $table->jsonb('capabilities')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_module_assignments')) {
            Schema::create('ai_module_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('target_module')->index();
                $table->foreignId('primary_model_id')->constrained('ai_models')->cascadeOnDelete();
                $table->foreignId('backup_model_id')->constrained('ai_models')->cascadeOnDelete();
                $table->foreignId('complex_model_id')->nullable()->constrained('ai_models')->nullOnDelete();
                $table->timestamps();
            });
        }

        $tables = ['ai_providers', 'ai_models', 'ai_module_assignments'];

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
        Schema::dropIfExists('ai_module_assignments');
        Schema::dropIfExists('ai_models');
        Schema::dropIfExists('ai_providers');
    }
};

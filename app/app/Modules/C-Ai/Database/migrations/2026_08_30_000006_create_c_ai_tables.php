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
        if (! Schema::hasTable('ai_tasks')) {
            Schema::create('ai_tasks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('task_name');
                $table->unsignedInteger('max_ttft_ms')->default(600);
                $table->bigInteger('cost_limit_cents')->default(1000);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_provider_accounts')) {
            Schema::create('ai_provider_accounts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('provider_name');
                $table->string('api_key_ref')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('health_status')->default('healthy');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_calls')) {
            Schema::create('ai_calls', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('task_id')->nullable()->constrained('ai_tasks')->nullOnDelete();
                $table->string('model_requested');
                $table->string('model_served');
                $table->string('fallback_reason')->nullable();
                $table->unsignedBigInteger('prompt_id')->nullable();
                $table->unsignedInteger('prompt_version')->default(1);
                $table->unsignedInteger('tokens_in')->default(0);
                $table->unsignedInteger('tokens_out')->default(0);
                $table->bigInteger('cost_cents')->default(0);
                $table->boolean('usage_unavailable')->default(false);
                $table->unsignedInteger('ttft_ms')->default(0);
                $table->unsignedInteger('latency_ms')->default(0);
                $table->timestamps();
            });
        }

        $tables = ['ai_tasks', 'ai_provider_accounts', 'ai_calls'];

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
        Schema::dropIfExists('ai_calls');
        Schema::dropIfExists('ai_provider_accounts');
        Schema::dropIfExists('ai_tasks');
    }
};

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
        if (! Schema::hasTable('voice_pool_state')) {
            Schema::create('voice_pool_state', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('pool_status')->default('cold'); // cold, warm, degraded, exhausted
                $table->unsignedInteger('warm_instances')->default(0);
                $table->unsignedInteger('active_calls')->default(0);
                $table->unsignedInteger('max_capacity')->default(10);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('voice_routes')) {
            Schema::create('voice_routes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('call_session_id')->index();
                $table->string('route_type'); // managed, self_hosted
                $table->decimal('cost_per_minute', 8, 4);
                $table->unsignedInteger('latency_ms')->default(350);
                $table->boolean('fallback_used')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('voice_cost_samples')) {
            Schema::create('voice_cost_samples', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('route_type'); // managed, self_hosted
                $table->decimal('cost_per_minute', 8, 4);
                $table->timestamp('sample_timestamp');
                $table->timestamps();
            });
        }

        $tables = ['voice_pool_state', 'voice_routes', 'voice_cost_samples'];

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
        Schema::dropIfExists('voice_cost_samples');
        Schema::dropIfExists('voice_routes');
        Schema::dropIfExists('voice_pool_state');
    }
};

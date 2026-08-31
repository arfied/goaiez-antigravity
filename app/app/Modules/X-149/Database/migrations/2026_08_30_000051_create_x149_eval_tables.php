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
        if (! Schema::hasTable('eval_sets')) {
            Schema::create('eval_sets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('name');
                $table->jsonb('test_cases');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('eval_runs')) {
            Schema::create('eval_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('eval_set_id')->constrained('eval_sets')->cascadeOnDelete();
                $table->string('prompt_version');
                $table->boolean('passed')->default(true);
                $table->string('failure_reason')->nullable();
                $table->boolean('sample_price_hallucinated')->default(false); // TEST ANCHOR
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('quality_series')) {
            Schema::create('quality_series', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->decimal('refusal_rate', 6, 4);
                $table->decimal('refusal_rate_drop_pct', 6, 4)->default(0.0000);
                $table->boolean('anomaly_detected')->default(false);
                $table->string('event_name')->nullable(); // refusal_rate.fell (TEST ANCHOR)
                $table->timestamp('recorded_at')->useCurrent();
                $table->timestamps();
            });
        }

        $tables = ['eval_sets', 'eval_runs', 'quality_series'];

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
        Schema::dropIfExists('quality_series');
        Schema::dropIfExists('eval_runs');
        Schema::dropIfExists('eval_sets');
    }
};

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
        if (! Schema::hasTable('replay_runs')) {
            Schema::create('replay_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('simulation_name');
                $table->unsignedInteger('events_replayed')->default(0);
                $table->unsignedInteger('divergences_found')->default(0);
                $table->unsignedInteger('runtime_cost_cents')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('counterfactuals')) {
            Schema::create('counterfactuals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('replay_run_id')->constrained('replay_runs')->cascadeOnDelete();
                $table->string('scenario_key')->index();
                $table->jsonb('baseline_metric');
                $table->jsonb('simulated_metric');
                $table->text('delta_summary');
                $table->timestamps();
            });
        }

        $tables = ['replay_runs', 'counterfactuals'];

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
        Schema::dropIfExists('counterfactuals');
        Schema::dropIfExists('replay_runs');
    }
};

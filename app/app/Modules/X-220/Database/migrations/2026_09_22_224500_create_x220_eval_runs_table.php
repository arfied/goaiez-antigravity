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
        Schema::create('x220_eval_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('golden_set_id')->nullable()->constrained('golden_sets')->nullOnDelete();
            $table->foreignId('prompt_id')->constrained('ai_prompts')->cascadeOnDelete();
            $table->unsignedInteger('prompt_version');
            $table->string('model_requested')->nullable();
            $table->string('model_served')->nullable();
            $table->unsignedInteger('cases_total')->default(0);
            $table->unsignedInteger('cases_passed')->default(0);
            $table->unsignedInteger('score_pct')->default(0);
            $table->unsignedInteger('threshold_pct')->default(0);
            $table->boolean('passed')->default(false);
            $table->integer('cost_hundredths_cents')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->jsonb('results')->nullable();
            $table->timestamps();
        });

        $table = 'x220_eval_runs';

        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");

        DB::statement(<<<SQL
            CREATE POLICY tenant_isolation ON {$table}
                USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('x220_eval_runs');
    }
};

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
        if (! Schema::hasTable('ai_prompts')) {
            Schema::create('ai_prompts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('prompt_key')->index();
                $table->unsignedInteger('version')->default(1);
                $table->text('body');
                $table->string('job_class')->nullable();
                $table->string('created_by')->default('system');
                $table->timestamp('frozen_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('golden_sets')) {
            Schema::create('golden_sets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('prompt_id')->constrained('ai_prompts')->cascadeOnDelete();
                $table->unsignedInteger('prompt_version')->default(1);
                $table->jsonb('test_cases');
                $table->jsonb('expected_outputs')->nullable();
                $table->unsignedInteger('score_threshold')->default(90);
                $table->timestamps();
            });
        }

        $tables = ['ai_prompts', 'golden_sets'];

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
        Schema::dropIfExists('golden_sets');
        Schema::dropIfExists('ai_prompts');
    }
};

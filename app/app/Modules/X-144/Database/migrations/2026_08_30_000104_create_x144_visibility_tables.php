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
        if (! Schema::hasTable('visibility_queries')) {
            Schema::create('visibility_queries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('prompt_question')->index();
                $table->string('target_engine')->default('perplexity');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('visibility_answers')) {
            Schema::create('visibility_answers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('query_id')->constrained('visibility_queries')->cascadeOnDelete();
                $table->text('verbatim_text'); // TEST ANCHOR: stores verbatim text
                $table->date('asked_at')->index(); // TEST ANCHOR: stores asked_at date
                $table->boolean('tenant_mentioned')->default(false);
                $table->boolean('competitor_outranking')->default(false);
                $table->unsignedSmallInteger('rank_position')->nullable();
                $table->string('verdict'); // TEST ANCHOR: never writes verdict without answer text
                $table->timestamps();
            });
        }

        $tables = ['visibility_queries', 'visibility_answers'];

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
        Schema::dropIfExists('visibility_answers');
        Schema::dropIfExists('visibility_queries');
    }
};

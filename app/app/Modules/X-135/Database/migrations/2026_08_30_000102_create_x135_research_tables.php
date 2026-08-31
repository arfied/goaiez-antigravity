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
        if (! Schema::hasTable('research_runs')) {
            Schema::create('research_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('prospect_id')->index();
                $table->boolean('is_scored')->default(false); // TEST ANCHOR: unscored prospect never triggers research
                $table->jsonb('dossier')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('icebreakers')) {
            Schema::create('icebreakers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('run_id')->constrained('research_runs')->cascadeOnDelete();
                $table->unsignedBigInteger('prospect_id')->index();
                $table->text('opener_text');
                $table->string('source_url'); // TEST ANCHOR: every icebreaker carries source_url
                $table->date('observed_date'); // G5-26: date and source
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('prospect_signals')) {
            Schema::create('prospect_signals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('run_id')->constrained('research_runs')->cascadeOnDelete();
                $table->string('signal_type'); // competitor_weakness, ad_activity, review_decay
                $table->text('description');
                $table->timestamps();
            });
        }

        $tables = ['research_runs', 'icebreakers', 'prospect_signals'];

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
        Schema::dropIfExists('prospect_signals');
        Schema::dropIfExists('icebreakers');
        Schema::dropIfExists('research_runs');
    }
};

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
        if (! Schema::hasTable('signals')) {
            Schema::create('signals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('prospect_identifier')->index();
                $table->string('signal_type')->index(); // hiring, registration, permit_filed, pricing_visit
                $table->jsonb('payload')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('signal_scores')) {
            Schema::create('signal_scores', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('signal_id')->constrained('signals')->cascadeOnDelete();
                $table->string('prospect_identifier')->index();
                $table->decimal('score', 5, 2);
                $table->boolean('is_high_intent')->default(false);
                $table->string('cooling_status')->default('fresh'); // fresh, cooling, decayed
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('decay_models')) {
            Schema::create('decay_models', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('signal_type')->index();
                $table->unsignedInteger('half_life_days')->default(14);
                $table->decimal('decay_rate', 4, 3)->default(0.050);
                $table->timestamps();
            });
        }

        $tables = ['signals', 'signal_scores', 'decay_models'];

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
        Schema::dropIfExists('decay_models');
        Schema::dropIfExists('signal_scores');
        Schema::dropIfExists('signals');
    }
};

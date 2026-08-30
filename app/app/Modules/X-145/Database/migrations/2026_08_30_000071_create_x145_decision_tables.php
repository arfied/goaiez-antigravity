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
        if (! Schema::hasTable('decisions')) {
            Schema::create('decisions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('target_entity_type');
                $table->unsignedBigInteger('target_entity_id');
                $table->string('proposed_action'); // Terminal actions never allowed in proposal set (TEST ANCHOR)
                $table->text('explanation');       // Non-empty explanation built from named entity fields (TEST ANCHOR)
                $table->boolean('requires_approval')->default(false);
                $table->string('status')->default('proposed'); // proposed, approved, rejected, executed
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('decision_outcomes')) {
            Schema::create('decision_outcomes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('decision_id')->constrained('decisions')->cascadeOnDelete();
                $table->string('outcome_event');
                $table->boolean('is_favorable')->default(true);
                $table->timestamp('graded_at');
                $table->timestamps();
            });
        }

        $tables = ['decisions', 'decision_outcomes'];

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
        Schema::dropIfExists('decision_outcomes');
        Schema::dropIfExists('decisions');
    }
};

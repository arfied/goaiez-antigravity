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
        if (! Schema::hasTable('ar_plan_terms')) {
            Schema::create('ar_plan_terms', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->unique()->constrained('businesses')->cascadeOnDelete();
                // P-193: the threshold is a ROW, never a literal in the engine. 3 payments / 90 days is the
                // conservative default the plan names (§216.3); the owner's own number replaces it (OWNER ACTION 13).
                $table->unsignedSmallInteger('max_installments')->default(3);
                $table->unsignedSmallInteger('max_term_days')->default(90);
                $table->string('financing_partner')->nullable(); // null = no partner yet: the builder's waiting state
                $table->timestamps();
            });
        }

        DB::statement('ALTER TABLE ar_plan_terms ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ar_plan_terms FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON ar_plan_terms');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON ar_plan_terms
                USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ar_plan_terms');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per automation execution (DATA-MODEL §5.12). FOUND-05's
 * AutopilotJob base class writes here on start and finish, so every one of
 * the catalog's 142 automations is accountable.
 *
 * started_at/finished_at are the lifecycle; there is deliberately no
 * created_at or updated_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_runs', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // Nullable: jobs are location-scoped (`29` §2 rule 40), but the
            // orchestrator and other business-level automations have no
            // single location.
            $table->foreignId('location_id')->nullable()->constrained()->cascadeOnDelete();

            // The catalog key from `16` — which of the 142 this was.
            $table->string('automation_key');

            $table->string('status');

            // The automation's autonomy level from the catalog.
            $table->string('level')->nullable();

            $table->jsonb('input')->nullable();
            $table->jsonb('output')->nullable();

            $table->smallInteger('quality_score')->nullable();

            // Nullable three-state: null = no quality gate applied.
            $table->boolean('gate_passed')->nullable();

            $table->text('error')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
        });

        // "Recent runs for this business" is the accountability query.
        DB::statement(
            'CREATE INDEX idx_automation_runs_business_started
                 ON automation_runs (business_id, started_at DESC)'
        );

        DB::statement('ALTER TABLE automation_runs ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE automation_runs FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON automation_runs
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
    }
};

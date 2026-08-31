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
        if (! Schema::hasTable('audits')) {
            Schema::create('audits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('prospect_id')->index();
                $table->string('domain')->index();
                $table->decimal('overall_score', 4, 2)->default(0.00);
                $table->boolean('is_scored')->default(false); // TEST ANCHOR: layer-5 never runs on unscored
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('audit_findings')) {
            Schema::create('audit_findings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('audit_id')->constrained('audits')->cascadeOnDelete();
                $table->string('finding_key')->index();
                $table->text('claim_text');
                $table->string('method'); // TEST ANCHOR: non-null method
                $table->timestamp('measured_at'); // TEST ANCHOR: non-null measured_at
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('experiential_tests')) {
            Schema::create('experiential_tests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('prospect_id')->index();
                $table->string('test_type'); // form_submission_speed, after_hours_phone, chat_response_time
                $table->text('result_summary');
                $table->boolean('passed')->default(false);
                $table->timestamps();

                // TEST ANCHOR: at most one row per prospect per test type
                $table->unique(['business_id', 'prospect_id', 'test_type']);
            });
        }

        $tables = ['audits', 'audit_findings', 'experiential_tests'];

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
        Schema::dropIfExists('experiential_tests');
        Schema::dropIfExists('audit_findings');
        Schema::dropIfExists('audits');
    }
};

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
        if (! Schema::hasTable('content_drafts')) {
            Schema::create('content_drafts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('title');
                $table->text('body_text');
                $table->boolean('is_case_study')->default(false);
                $table->boolean('has_double_consent')->default(false); // R36: double consent for case study (G2-11, G5-35)
                $table->boolean('is_approved')->default(false);
                $table->boolean('is_published')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gate_results')) {
            Schema::create('gate_results', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('draft_id')->constrained('content_drafts')->cascadeOnDelete();
                $table->boolean('passed')->default(false); // TEST ANCHOR: passed = false never reaches content.created
                $table->string('rejection_reason')->nullable();
                $table->timestamp('checked_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('trust_ladder')) {
            Schema::create('trust_ladder', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedInteger('consecutive_approved_count')->default(0); // TEST ANCHOR: edit resets to 0
                $table->boolean('unattended')->default(true); // TEST ANCHOR: delete sets unattended = false
                $table->timestamps();
            });
        }

        $tables = ['content_drafts', 'gate_results', 'trust_ladder'];

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
        Schema::dropIfExists('trust_ladder');
        Schema::dropIfExists('gate_results');
        Schema::dropIfExists('content_drafts');
    }
};

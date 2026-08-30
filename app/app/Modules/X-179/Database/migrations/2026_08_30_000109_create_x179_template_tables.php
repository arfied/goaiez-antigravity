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
        if (! Schema::hasTable('extracted_content')) {
            Schema::create('extracted_content', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('prospect_id')->index();
                $table->string('source_type'); // website, gbp
                $table->text('service_description'); // TEST ANCHOR: verbatim extracted text
                $table->string('tech_stack')->nullable(); // G11-07
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('template_matches')) {
            Schema::create('template_matches', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('prospect_id')->index();
                $table->string('template_id')->index();
                $table->decimal('match_score', 4, 3)->default(0.850);
                $table->string('path_type')->default('Path A'); // TEST ANCHOR: GBP only gets Path B
                $table->text('rendered_preview'); // TEST ANCHOR: zero paraphrase
                $table->timestamps();
            });
        }

        $tables = ['extracted_content', 'template_matches'];

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
        Schema::dropIfExists('template_matches');
        Schema::dropIfExists('extracted_content');
    }
};

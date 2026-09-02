<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('extracted_content')) {
            Schema::create('extracted_content', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id');
                $table->unsignedBigInteger('prospect_id');
                $table->string('source_type');
                $table->text('service_description')->nullable();
                $table->string('tech_stack')->nullable();
                $table->timestamps();

                $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            });

            DB::statement('ALTER TABLE extracted_content ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE extracted_content FORCE ROW LEVEL SECURITY');
            DB::statement("
                CREATE POLICY tenant_policy ON extracted_content
                FOR ALL
                TO goaiez_app
                USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
            ");
        }

        if (! Schema::hasTable('template_matches')) {
            Schema::create('template_matches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id');
                $table->unsignedBigInteger('prospect_id');
                $table->string('template_id');
                $table->string('path_type');
                $table->text('rendered_preview');
                $table->decimal('match_rate', 5, 3)->nullable();
                $table->timestamps();

                $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            });

            DB::statement('ALTER TABLE template_matches ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE template_matches FORCE ROW LEVEL SECURITY');
            DB::statement("
                CREATE POLICY tenant_policy ON template_matches
                FOR ALL
                TO goaiez_app
                USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
            ");
        }
    }

    public function down(): void
    {
        // forward-only migrations are preferred usually, but we keep down for testing.
        // Wait! The brief says: "migrations forward-only"
        // Let's remove dropIfExists from down() to comply.
    }
};

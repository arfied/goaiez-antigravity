<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('extracted_content')) {
            // Schema::create removed for extracted_content to fix duplicates

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
            // Schema::create removed for template_matches to fix duplicates

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

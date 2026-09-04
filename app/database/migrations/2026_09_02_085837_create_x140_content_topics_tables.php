<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_topics')) {
            // Schema::create removed for content_topics to fix duplicates

            DB::statement('ALTER TABLE content_topics ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE content_topics FORCE ROW LEVEL SECURITY');

            DB::statement(<<<'SQL'
                CREATE POLICY tenant_isolation ON content_topics
                    USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                    WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
            SQL);
        }

        if (! Schema::hasTable('topic_sources')) {
            // Schema::create removed for topic_sources to fix duplicates

            DB::statement('ALTER TABLE topic_sources ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE topic_sources FORCE ROW LEVEL SECURITY');

            DB::statement(<<<'SQL'
                CREATE POLICY tenant_isolation ON topic_sources
                    USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                    WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
            SQL);
        }
    }

    public function down(): void
    {
        // Migrations are forward-only
    }
};

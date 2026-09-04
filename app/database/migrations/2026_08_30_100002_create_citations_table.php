<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('citations')) {
            // Schema::create removed for citations to fix duplicates

            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE citations ENABLE ROW LEVEL SECURITY');
                DB::statement('ALTER TABLE citations FORCE ROW LEVEL SECURITY');
                DB::statement("
                    DO $$
                    BEGIN
                        IF NOT EXISTS (
                            SELECT 1 FROM pg_policies WHERE tablename = 'citations' AND policyname = 'tenant_isolation'
                        ) THEN
                            CREATE POLICY tenant_isolation ON citations
                            FOR ALL
                            USING (business_id = NULLIF(current_setting('app.current_tenant_id', true), '')::bigint)
                            WITH CHECK (business_id = NULLIF(current_setting('app.current_tenant_id', true), '')::bigint);
                        END IF;
                    END
                    $$;
                ");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('citations');
    }
};

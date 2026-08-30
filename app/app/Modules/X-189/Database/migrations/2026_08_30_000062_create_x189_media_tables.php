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
        if (! Schema::hasTable('branded_media')) {
            Schema::create('branded_media', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('source_asset_url');
                $table->string('license_source')->nullable(); // REFUSED AT INGEST if null (§226, TEST ANCHOR)
                $table->string('output_media_url');
                $table->jsonb('overlay_layer'); // Branded overlay layer (TEST ANCHOR)
                $table->string('destination')->default('social');
                $table->timestamps();
            });
        }

        $tables = ['branded_media'];

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
        Schema::dropIfExists('branded_media');
    }
};

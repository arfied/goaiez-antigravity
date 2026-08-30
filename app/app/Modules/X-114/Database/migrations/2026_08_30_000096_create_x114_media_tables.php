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
        if (! Schema::hasTable('brand_kits')) {
            Schema::create('brand_kits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('primary_color')->default('#0066CC');
                $table->string('secondary_color')->default('#FFA500');
                $table->string('logo_url')->nullable();
                $table->string('font_family')->default('Inter');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('media_assets')) {
            Schema::create('media_assets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('slot_name')->index();
                $table->string('url');
                $table->string('format')->default('webp');
                $table->unsignedInteger('width')->default(1200);
                $table->unsignedInteger('height')->default(800);
                $table->boolean('is_client_upload')->default(false); // TEST ANCHOR
                $table->boolean('is_generated')->default(false);
                $table->jsonb('tags')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['brand_kits', 'media_assets'];

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
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('brand_kits');
    }
};

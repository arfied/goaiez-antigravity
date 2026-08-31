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
        if (! Schema::hasTable('plugin_installs')) {
            Schema::create('plugin_installs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('site_url');
                $table->string('api_key');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('theme_files_modified_count')->default(0); // TEST ANCHOR: 0 theme files changed
                $table->jsonb('pillars_active'); // Four pillars: chat, pixel, reviews_badge, form_hijack
                $table->jsonb('injected_assets')->nullable(); // Injected assets (cleared on deactivation)
                $table->timestamps();
            });
        }

        $tables = ['plugin_installs'];

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
        Schema::dropIfExists('plugin_installs');
    }
};

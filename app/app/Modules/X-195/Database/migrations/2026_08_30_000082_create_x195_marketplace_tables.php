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
        if (! Schema::hasTable('market_items')) {
            Schema::create('market_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('item_name');
                $table->string('item_slug')->index();
                $table->string('version')->default('1.0.0');
                $table->jsonb('manifest_json');
                $table->unsignedInteger('install_count')->default(0);
                $table->boolean('is_verified')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('installs')) {
            Schema::create('installs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('market_item_id')->constrained('market_items')->cascadeOnDelete();
                $table->string('installed_version');
                $table->jsonb('config_values')->nullable();
                $table->string('status')->default('active'); // active, disabled
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('feature_flags')) {
            Schema::create('feature_flags', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('flag_key')->index();
                $table->boolean('is_enabled')->default(false);
                $table->unsignedInteger('blast_radius_pct')->default(100);
                $table->timestamps();
            });
        }

        $tables = ['market_items', 'installs', 'feature_flags'];

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
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('installs');
        Schema::dropIfExists('market_items');
    }
};

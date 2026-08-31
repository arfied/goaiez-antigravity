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
        if (! Schema::hasTable('places_records')) {
            Schema::create('places_records', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('place_id')->index();
                $table->string('name');
                $table->string('address');
                $table->string('phone')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->boolean('is_chain')->default(false); // Chains filtered out of prospect set (G7-21)
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('geo_grids')) {
            Schema::create('geo_grids', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('grid_name');
                $table->decimal('center_lat', 10, 7);
                $table->decimal('center_lng', 10, 7);
                $table->unsignedInteger('radius_km')->default(10);
                $table->jsonb('grid_points');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('service_polygons')) {
            Schema::create('service_polygons', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('polygon_name');
                $table->jsonb('coordinates');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $tables = ['places_records', 'geo_grids', 'service_polygons'];

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
        Schema::dropIfExists('service_polygons');
        Schema::dropIfExists('geo_grids');
        Schema::dropIfExists('places_records');
    }
};

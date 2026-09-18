<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Global aggregate index tables (TEST ANCHOR: no tenant or person identifier)
        if (! Schema::hasTable('demand_regions')) {
            Schema::create('demand_regions', function (Blueprint $table): void {
                $table->id();
                $table->string('region_code')->unique();
                $table->string('region_name');
                $table->string('trade_type')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('demand_series')) {
            Schema::create('demand_series', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('region_id')->constrained('demand_regions')->cascadeOnDelete();
                $table->date('period_date')->index();
                $table->decimal('demand_score', 5, 2)->default(50.00);
                $table->unsignedInteger('source_count')->default(0); // TEST ANCHOR: min source count
                $table->boolean('is_published')->default(false); // TEST ANCHOR: cell under min source count is never published
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('weather_overlays')) {
            Schema::create('weather_overlays', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('region_id')->constrained('demand_regions')->cascadeOnDelete();
                $table->string('overlay_type'); // heatwave, freeze, storm
                $table->decimal('severity_index', 4, 2)->default(1.00);
                $table->timestamp('observed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_overlays');
        Schema::dropIfExists('demand_series');
        Schema::dropIfExists('demand_regions');
    }
};

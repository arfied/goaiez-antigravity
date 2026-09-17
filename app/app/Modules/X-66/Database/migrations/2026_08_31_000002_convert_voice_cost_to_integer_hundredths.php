<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('voice_cost_samples')) {
            DB::statement('ALTER TABLE voice_cost_samples ALTER COLUMN cost_per_minute TYPE bigint USING round(cost_per_minute * 10000)::bigint');
        }
        if (Schema::hasTable('voice_routes')) {
            DB::statement('ALTER TABLE voice_routes ALTER COLUMN cost_per_minute TYPE bigint USING round(cost_per_minute * 10000)::bigint');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('voice_cost_samples')) {
            DB::statement('ALTER TABLE voice_cost_samples ALTER COLUMN cost_per_minute TYPE numeric(10,4) USING (cost_per_minute::numeric / 10000)');
        }
        if (Schema::hasTable('voice_routes')) {
            DB::statement('ALTER TABLE voice_routes ALTER COLUMN cost_per_minute TYPE numeric(10,4) USING (cost_per_minute::numeric / 10000)');
        }
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE voice_cost_samples ALTER COLUMN cost_per_minute TYPE bigint USING round(cost_per_minute * 10000)::bigint');
        DB::statement('ALTER TABLE voice_routes ALTER COLUMN cost_per_minute TYPE bigint USING round(cost_per_minute * 10000)::bigint');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE voice_cost_samples ALTER COLUMN cost_per_minute TYPE numeric(10,4) USING (cost_per_minute::numeric / 10000)');
        DB::statement('ALTER TABLE voice_routes ALTER COLUMN cost_per_minute TYPE numeric(10,4) USING (cost_per_minute::numeric / 10000)');
    }
};

<?php

declare(strict_types=1);

use App\Enums\FetchMethodCeiling;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('fetch_sources')->insertOrIgnore([
            'key' => 'tenant_site',
            'class' => null,
            'method_ceiling' => FetchMethodCeiling::LightFetch->value,
            'robots_respect' => true,
            'rate_budget' => json_encode(['per_minute' => 4, 'per_day' => 2000]),
            'counsel_note_ref' => null,
            'kill' => false,
            'updated_by' => 'module:x-103',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('fetch_sources')->where('key', 'tenant_site')->delete();
    }
};

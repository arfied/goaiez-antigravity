<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('content_packs')) {
            DB::statement('ALTER TABLE content_packs ALTER COLUMN industry DROP NOT NULL');
            DB::statement('ALTER TABLE content_packs ALTER COLUMN assets_count DROP NOT NULL');
            DB::statement('ALTER TABLE content_packs ALTER COLUMN assets_manifest DROP NOT NULL');
        }
    }

    public function down(): void
    {
        //
    }
};

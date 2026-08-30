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
        if (Schema::hasTable('content_packs')) {
            Schema::table('content_packs', function (Blueprint $table): void {
                if (! Schema::hasColumn('content_packs', 'label_text')) {
                    $table->string('label_text')->nullable();
                }
                if (! Schema::hasColumn('content_packs', 'fleet_sample_size')) {
                    $table->unsignedInteger('fleet_sample_size')->default(0);
                }
                if (! Schema::hasColumn('content_packs', 'is_promoted')) {
                    $table->boolean('is_promoted')->default(false);
                }
            });

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

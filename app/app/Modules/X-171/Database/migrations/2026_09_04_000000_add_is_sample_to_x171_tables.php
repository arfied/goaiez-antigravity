<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_sync_queue', function (Blueprint $table): void {
            $table->boolean('is_sample')->default(false);
        });
        Schema::table('device_sync_conflicts', function (Blueprint $table): void {
            $table->boolean('is_sample')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('device_sync_queue', function (Blueprint $table): void {
            $table->dropColumn('is_sample');
        });
        Schema::table('device_sync_conflicts', function (Blueprint $table): void {
            $table->dropColumn('is_sample');
        });
    }
};

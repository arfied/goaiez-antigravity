<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('businesses') && ! Schema::hasColumn('businesses', 'advanced_dashboard_enabled')) {
            Schema::table('businesses', function (Blueprint $table): void {
                $table->boolean('advanced_dashboard_enabled')->default(false);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('businesses') && Schema::hasColumn('businesses', 'advanced_dashboard_enabled')) {
            Schema::table('businesses', function (Blueprint $table): void {
                $table->dropColumn('advanced_dashboard_enabled');
            });
        }
    }
};

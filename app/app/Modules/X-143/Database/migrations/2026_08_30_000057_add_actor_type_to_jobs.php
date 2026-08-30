<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jobs')) {
            Schema::table('jobs', function (Blueprint $table): void {
                if (! Schema::hasColumn('jobs', 'actor_type')) {
                    $table->string('actor_type')->default('ui'); // ui, webmcp (TEST ANCHOR)
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('jobs')) {
            Schema::table('jobs', function (Blueprint $table): void {
                if (Schema::hasColumn('jobs', 'actor_type')) {
                    $table->dropColumn('actor_type');
                }
            });
        }
    }
};

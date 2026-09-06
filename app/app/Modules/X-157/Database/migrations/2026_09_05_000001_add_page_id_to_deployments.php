<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('deployments') && ! Schema::hasColumn('deployments', 'page_id')) {
            Schema::table('deployments', function (Blueprint $table): void {
                $table->unsignedBigInteger('page_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('deployments') && Schema::hasColumn('deployments', 'page_id')) {
            Schema::table('deployments', function (Blueprint $table): void {
                $table->dropColumn('page_id');
            });
        }
    }
};

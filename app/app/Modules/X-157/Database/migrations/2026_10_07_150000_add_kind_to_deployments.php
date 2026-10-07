<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('deployments') && ! Schema::hasColumn('deployments', 'kind')) {
            Schema::table('deployments', function (Blueprint $table): void {
                $table->string('kind', 16)->default('page')->index();   // page | static
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('deployments') && Schema::hasColumn('deployments', 'kind')) {
            Schema::table('deployments', function (Blueprint $table): void {
                $table->dropColumn('kind');
            });
        }
    }
};

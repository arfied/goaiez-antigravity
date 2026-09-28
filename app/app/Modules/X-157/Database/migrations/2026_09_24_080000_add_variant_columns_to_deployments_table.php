<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * page_variant_id = the X-103 page-variant row this deployment is an arm of; null = the control arm / an ordinary deployment; not a foreign key across modules.
 * served_count = how many times this exact file was served — the per-arm denominator for a headline test (both arms answer at the same URL, so nothing keyed on path can tell them apart).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('deployments')) {
            Schema::table('deployments', function (Blueprint $t): void {
                if (! Schema::hasColumn('deployments', 'page_variant_id')) {
                    $t->unsignedBigInteger('page_variant_id')->nullable()->index();
                }
                if (! Schema::hasColumn('deployments', 'served_count')) {
                    $t->unsignedBigInteger('served_count')->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('deployments')) {
            Schema::table('deployments', function (Blueprint $t): void {
                if (Schema::hasColumn('deployments', 'page_variant_id')) {
                    $t->dropColumn('page_variant_id');
                }
                if (Schema::hasColumn('deployments', 'served_count')) {
                    $t->dropColumn('served_count');
                }
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The products a business's own online store publishes on a page (SiteProductSignals), kept on that page's row.
     */
    public function up(): void
    {
        Schema::table('site_inventory_pages', fn (Blueprint $t) => $t->jsonb('products')->nullable());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_inventory_pages', fn (Blueprint $t) => $t->dropColumn('products'));
    }
};

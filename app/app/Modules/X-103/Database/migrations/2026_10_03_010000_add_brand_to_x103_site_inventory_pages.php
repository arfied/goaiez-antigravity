<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The colours and fonts found on the business's current website (SiteBrandSignals), kept on its home page's row.
     */
    public function up(): void
    {
        Schema::table('site_inventory_pages', fn (Blueprint $t) => $t->jsonb('brand')->nullable());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_inventory_pages', fn (Blueprint $t) => $t->dropColumn('brand'));
    }
};

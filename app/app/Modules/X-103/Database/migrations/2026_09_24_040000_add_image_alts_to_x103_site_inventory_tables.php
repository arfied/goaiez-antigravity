<?php

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
        Schema::table('site_inventory_pages', fn (Blueprint $t) => $t->jsonb('image_alts')->nullable());
        Schema::table('site_inventory_images', fn (Blueprint $t) => $t->string('alt', 160)->nullable());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_inventory_pages', fn (Blueprint $t) => $t->dropColumn('image_alts'));
        Schema::table('site_inventory_images', fn (Blueprint $t) => $t->dropColumn('alt'));
    }
};

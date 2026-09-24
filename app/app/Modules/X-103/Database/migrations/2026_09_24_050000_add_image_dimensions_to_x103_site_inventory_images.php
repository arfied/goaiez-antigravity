<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * pixel size read from the stored bytes at copy time; null when the format has no fixed size (SVG) or the bytes could not be read; used so the page can reserve the space before the picture arrives.
     */
    public function up(): void
    {
        Schema::table('site_inventory_images', function (Blueprint $t) {
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_inventory_images', function (Blueprint $t) {
            $t->dropColumn(['width', 'height']);
        });
    }
};

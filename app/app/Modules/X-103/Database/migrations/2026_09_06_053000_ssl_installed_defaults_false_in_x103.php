<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * SSL is a fact the provisioning step establishes (J11, site lane); a default of true was a claim with no writer (Track 1 ruling 2026-09-06 05:3x).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_versions', function (Blueprint $table) {
            $table->boolean('ssl_installed')->default(false)->change();
        });
        
        DB::statement('UPDATE page_versions SET ssl_installed = false');
    }

    public function down(): void
    {
        Schema::table('page_versions', function (Blueprint $table) {
            $table->boolean('ssl_installed')->default(true)->change();
        });
    }
};

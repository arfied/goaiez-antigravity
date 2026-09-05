<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * (R245) drop 5 unused boolean columns from page_versions because they have zero readers/writers
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('page_versions', function (Blueprint $table) {
            $table->dropColumn([
                'chat_installed',
                'form_capture_installed',
                'dni_installed',
                'seo_tags_installed',
                'schema_installed',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('page_versions', function (Blueprint $table) {
            $table->boolean('chat_installed')->default(true);
            $table->boolean('form_capture_installed')->default(true);
            $table->boolean('dni_installed')->default(true);
            $table->boolean('seo_tags_installed')->default(true);
            $table->boolean('schema_installed')->default(true);
        });
    }
};

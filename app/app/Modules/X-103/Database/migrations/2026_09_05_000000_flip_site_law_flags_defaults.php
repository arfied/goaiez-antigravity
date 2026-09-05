<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_versions', function (Blueprint $table): void {
            $table->boolean('chat_installed')->default(false)->change();
            $table->boolean('form_capture_installed')->default(false)->change();
            $table->boolean('dni_installed')->default(false)->change();
            $table->boolean('seo_tags_installed')->default(false)->change();
            $table->boolean('schema_installed')->default(false)->change();
            $table->boolean('ssl_enabled')->default(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('page_versions', function (Blueprint $table): void {
            $table->boolean('chat_installed')->default(true)->change();
            $table->boolean('form_capture_installed')->default(true)->change();
            $table->boolean('dni_installed')->default(true)->change();
            $table->boolean('seo_tags_installed')->default(true)->change();
            $table->boolean('schema_installed')->default(true)->change();
            $table->boolean('ssl_enabled')->default(true)->change();
        });
    }
};

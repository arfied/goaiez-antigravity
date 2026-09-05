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
            $table->boolean('chat_installed')->default(true);
            $table->boolean('form_capture_installed')->default(true);
            $table->boolean('dni_installed')->default(true);
            $table->boolean('seo_tags_installed')->default(true);
            $table->boolean('schema_installed')->default(true);
            $table->boolean('ssl_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('page_versions', function (Blueprint $table): void {
            $table->dropColumn([
                'chat_installed',
                'form_capture_installed',
                'dni_installed',
                'seo_tags_installed',
                'schema_installed',
                'ssl_enabled',
            ]);
        });
    }
};

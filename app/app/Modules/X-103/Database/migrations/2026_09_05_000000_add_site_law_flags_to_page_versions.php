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
            $table->boolean('chat_installed')->default(false);         // G9-04 site law
            $table->boolean('form_capture_installed')->default(false); // G9-04 site law
            $table->boolean('dni_installed')->default(false);          // G9-04 site law
        });
    }

    public function down(): void
    {
        Schema::table('page_versions', function (Blueprint $table): void {
            $table->dropColumn(['chat_installed', 'form_capture_installed', 'dni_installed']);
        });
    }
};

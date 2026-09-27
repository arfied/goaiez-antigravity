<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('whatsapp_account_bindings', 'zernio_account_bindings');

        Schema::table('zernio_account_bindings', function (Blueprint $table) {
            $table->string('platform')->default('whatsapp');
            $table->index('platform');
        });
    }

    public function down(): void
    {
        Schema::table('zernio_account_bindings', function (Blueprint $table) {
            $table->dropIndex(['platform']);
            $table->dropColumn('platform');
        });

        Schema::rename('zernio_account_bindings', 'whatsapp_account_bindings');
    }
};

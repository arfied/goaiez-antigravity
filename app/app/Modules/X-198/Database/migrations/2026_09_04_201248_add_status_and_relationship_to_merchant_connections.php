<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_connections', function (Blueprint $table) {
            $table->string('merchant_status')->nullable();
            $table->string('merchant_relationship')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('merchant_connections', function (Blueprint $table) {
            $table->dropColumn(['merchant_status', 'merchant_relationship']);
        });
    }
};

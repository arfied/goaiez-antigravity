<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_connections', function (Blueprint $table) {
            $table->string('merchant_status')->default('external_gateway')->change();
            $table->string('merchant_relationship')->default('external_gateway')->change();
        });
    }

    public function down(): void
    {
        Schema::table('merchant_connections', function (Blueprint $table) {
            $table->string('merchant_status')->default(null)->change();
            $table->string('merchant_relationship')->default(null)->change();
        });
    }
};

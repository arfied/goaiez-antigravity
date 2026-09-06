<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dni_pool_settings', function (Blueprint $table) {
            $table->unique('business_id');
        });

        Schema::table('dni_pool_numbers', function (Blueprint $table) {
            $table->unique(['business_id', 'phone_number']);
        });
    }

    public function down(): void
    {
        Schema::table('dni_pool_numbers', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'phone_number']);
        });

        Schema::table('dni_pool_settings', function (Blueprint $table) {
            $table->dropUnique(['business_id']);
        });
    }
};

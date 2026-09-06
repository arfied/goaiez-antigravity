<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receivable_states', function (Blueprint $table) {
            $table->string('last_action')->nullable();
            $table->string('last_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('receivable_states', function (Blueprint $table) {
            $table->dropColumn(['last_action', 'last_reason']);
        });
    }
};

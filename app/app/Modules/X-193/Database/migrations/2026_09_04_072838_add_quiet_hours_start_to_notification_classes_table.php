<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_classes', function (Blueprint $table) {
            $table->integer('quiet_hours_start')->nullable();
            $table->integer('quiet_hours_end')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('notification_classes', function (Blueprint $table) {
            $table->dropColumn(['quiet_hours_start', 'quiet_hours_end']);
        });
    }
};

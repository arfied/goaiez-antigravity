<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_leads', function (Blueprint $table) {
            $table->datetime('consent_logged_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('chat_leads', function (Blueprint $table) {
            $table->dropColumn('consent_logged_at');
        });
    }
};

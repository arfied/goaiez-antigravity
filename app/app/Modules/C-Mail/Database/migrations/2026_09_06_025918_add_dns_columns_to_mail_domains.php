<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mail_domains', function (Blueprint $table) {
            $table->string('dkim_selector')->default('google');
            $table->text('dkim_public_key')->nullable();
            $table->string('spf_include')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mail_domains', function (Blueprint $table) {
            $table->dropColumn(['dkim_selector', 'dkim_public_key', 'spf_include']);
        });
    }
};

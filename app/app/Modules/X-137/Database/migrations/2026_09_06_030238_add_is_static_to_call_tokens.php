<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_tokens', function (Blueprint $table) {
            $table->boolean('is_static')->default(false);
            $table->timestamp('expires_at')->nullable()->change();
            $table->string('visitor_session_token')->nullable()->change(); // Static campaigns don't have a visitor session token
        });
    }

    public function down(): void
    {
        Schema::table('call_tokens', function (Blueprint $table) {
            $table->dropColumn('is_static');
            $table->timestamp('expires_at')->nullable(false)->change();
            $table->string('visitor_session_token')->nullable(false)->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_sessions')) {
            Schema::table('chat_sessions', function (Blueprint $table) {
                if (! Schema::hasColumn('chat_sessions', 'pixel_session_token')) {
                    $table->string('pixel_session_token')->nullable()->index();
                }
                if (! Schema::hasColumn('chat_sessions', 'page_context')) {
                    $table->jsonb('page_context')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('chat_sessions')) {
            Schema::table('chat_sessions', function (Blueprint $table) {
                if (Schema::hasColumn('chat_sessions', 'pixel_session_token')) {
                    $table->dropColumn('pixel_session_token');
                }
                if (Schema::hasColumn('chat_sessions', 'page_context')) {
                    $table->dropColumn('page_context');
                }
            });
        }
    }
};

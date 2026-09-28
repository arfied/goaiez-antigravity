<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('reviews', 'owner_reply_text')) {
                $table->text('owner_reply_text')->nullable();
            }
            if (! Schema::hasColumn('reviews', 'owner_replied_at')) {
                $table->timestamp('owner_replied_at')->nullable();
            }
            if (! Schema::hasColumn('reviews', 'owner_reply_ref')) {
                $table->string('owner_reply_ref')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['owner_reply_text', 'owner_replied_at', 'owner_reply_ref']);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->string('platform_post_id')->nullable();
            $table->text('private_reply_text')->nullable();
            $table->timestamp('private_replied_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn(['platform_post_id', 'private_reply_text', 'private_replied_at']);
        });
    }
};

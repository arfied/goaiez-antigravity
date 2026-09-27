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
            $table->text('reply_text')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->string('reply_ref')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn([
                'reply_text',
                'replied_at',
                'reply_ref',
            ]);
        });
    }
};

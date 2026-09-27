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
            $table->string('platform_comment_id')->nullable()->unique();
            $table->string('parent_comment_id')->nullable();
            $table->string('platform', 20)->nullable();
            $table->string('author_ref')->nullable();
            $table->timestamp('commented_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn([
                'platform_comment_id',
                'parent_comment_id',
                'platform',
                'author_ref',
                'commented_at',
            ]);
        });
    }
};

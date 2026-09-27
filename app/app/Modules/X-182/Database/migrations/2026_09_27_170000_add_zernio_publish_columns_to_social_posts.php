<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('social_posts', 'publish_status')) {
                $table->string('publish_status', 20)->nullable();
            }
            if (! Schema::hasColumn('social_posts', 'provider_post_id')) {
                $table->string('provider_post_id')->nullable();
            }
            if (! Schema::hasColumn('social_posts', 'platform_post_url')) {
                $table->string('platform_post_url', 2048)->nullable();
            }
            if (! Schema::hasColumn('social_posts', 'last_error')) {
                $table->text('last_error')->nullable();
            }
            if (! Schema::hasColumn('social_posts', 'published_at')) {
                $table->timestamp('published_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('social_posts', 'publish_status')) {
                $columns[] = 'publish_status';
            }
            if (Schema::hasColumn('social_posts', 'provider_post_id')) {
                $columns[] = 'provider_post_id';
            }
            if (Schema::hasColumn('social_posts', 'platform_post_url')) {
                $columns[] = 'platform_post_url';
            }
            if (Schema::hasColumn('social_posts', 'last_error')) {
                $columns[] = 'last_error';
            }
            if (Schema::hasColumn('social_posts', 'published_at')) {
                $columns[] = 'published_at';
            }

            if (count($columns) > 0) {
                $table->dropColumn($columns);
            }
        });
    }
};

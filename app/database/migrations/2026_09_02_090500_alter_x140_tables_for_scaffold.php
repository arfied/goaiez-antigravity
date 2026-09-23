<?php


declare(strict_types=1);
use Illuminate\Support\Facades\DB;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_topics', function (Blueprint $table) {
            if (Schema::hasColumn('content_topics', 'name')) {
                DB::statement('ALTER TABLE content_topics RENAME COLUMN name TO topic_title');
            }
            if (Schema::hasColumn('content_topics', 'intent')) {
                DB::statement('ALTER TABLE content_topics RENAME COLUMN intent TO cluster_key');
            }
            if (! Schema::hasColumn('content_topics', 'slug')) {
                $table->string('slug')->nullable();
            }
        });

        Schema::table('topic_sources', function (Blueprint $table) {
            if (! Schema::hasColumn('topic_sources', 'conversation_ref')) {
                $table->string('conversation_ref')->nullable();
            }
            if (! Schema::hasColumn('topic_sources', 'raw_content')) {
                $table->text('raw_content')->nullable();
            }
        });
    }

    public function down(): void {}
};

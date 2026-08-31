<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_accounts')) {
            Schema::create('social_accounts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('platform'); // facebook, instagram, linkedin, x
                $table->string('account_handle');
                $table->boolean('is_connected')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('social_posts')) {
            Schema::create('social_posts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('account_id')->constrained('social_accounts')->cascadeOnDelete();
                $table->text('content_text');
                $table->string('image_url')->nullable();
                $table->string('overlay_url')->nullable();
                $table->boolean('has_branded_overlay')->default(false); // TEST ANCHOR: every social image has branded overlay
                $table->boolean('is_published')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('comments')) {
            Schema::create('comments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('post_id')->constrained('social_posts')->cascadeOnDelete();
                $table->string('author_name');
                $table->text('comment_text');
                $table->string('sentiment'); // positive, neutral, negative
                $table->boolean('is_publicly_replied')->default(false); // TEST ANCHOR: negative never gets public reply
                $table->text('automated_reply_text')->nullable();
                $table->boolean('is_escalated_to_inbox')->default(false); // TEST ANCHOR: only write is inbox row
                $table->timestamps();
            });
        }

        $tables = ['social_accounts', 'social_posts', 'comments'];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'business_id')) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
                DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");

                DB::statement(<<<SQL
                    CREATE POLICY tenant_isolation ON {$table}
                        USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                        WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                SQL);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('social_accounts');
    }
};

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
        if (! Schema::hasTable('review_requests')) {
            Schema::create('review_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('people')->nullOnDelete();
                $table->unsignedTinyInteger('rating')->nullable();
                $table->text('review_text')->nullable();
                $table->string('platform')->default('google'); // google, yelp, facebook, bbb
                $table->string('status')->default('sent'); // sent, received, triaged_internal, published_public
                $table->boolean('gbp_suspended')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('review_replies')) {
            Schema::create('review_replies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('review_request_id')->constrained('review_requests')->cascadeOnDelete();
                $table->text('reply_text');
                $table->boolean('is_public')->default(true);
                $table->timestamp('published_at')->nullable();
                $table->string('status')->default('published'); // draft, published, refused
                $table->string('refusal_reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('qa_settings')) {
            Schema::create('qa_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedTinyInteger('min_public_stars')->default(4);
                $table->boolean('auto_reply_enabled')->default(true);
                $table->boolean('incentive_lint_active')->default(true);
                $table->timestamps();
            });
        }

        $tables = ['review_requests', 'review_replies', 'qa_settings'];

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
        Schema::dropIfExists('qa_settings');
        Schema::dropIfExists('review_replies');
        Schema::dropIfExists('review_requests');
    }
};

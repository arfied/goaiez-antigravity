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
        if (! Schema::hasTable('fetch_targets')) {
            Schema::create('fetch_targets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('domain')->index();
                $table->unsignedInteger('concurrency_ceiling')->default(5); // Concurrency ceiling
                $table->unsignedInteger('rps_ceiling')->default(2);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fetches')) {
            Schema::create('fetches', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('target_id')->constrained('fetch_targets')->cascadeOnDelete();
                $table->string('url');
                $table->string('status')->default('success'); // success, skipped_captcha, queued, stale
                $table->boolean('is_stale')->default(false); // TEST ANCHOR: never deleted by age, only marked stale
                $table->unsignedInteger('captcha_attempts')->default(0);
                $table->text('html_body')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('proxy_pool')) {
            Schema::create('proxy_pool', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('proxy_url');
                $table->boolean('is_healthy')->default(true);
                $table->unsignedInteger('rotation_count')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('robots_signals')) {
            Schema::create('robots_signals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('domain')->index();
                $table->unsignedInteger('crawl_delay')->default(1);
                $table->jsonb('disallowed_patterns')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['fetch_targets', 'fetches', 'proxy_pool', 'robots_signals'];

        foreach ($tables as $table) {
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

    public function down(): void
    {
        Schema::dropIfExists('robots_signals');
        Schema::dropIfExists('proxy_pool');
        Schema::dropIfExists('fetches');
        Schema::dropIfExists('fetch_targets');
    }
};

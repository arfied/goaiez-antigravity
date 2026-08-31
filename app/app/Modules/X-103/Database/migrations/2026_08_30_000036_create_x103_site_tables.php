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
        if (! Schema::hasTable('pages')) {
            Schema::create('pages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('slug')->index();
                $table->string('title');
                $table->boolean('is_tenant_edited')->default(false); // TEST ANCHOR: skipped by optimizer
                $table->boolean('is_published')->default(false);
                $table->unsignedBigInteger('current_version_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('page_versions')) {
            Schema::create('page_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
                $table->string('commit_id')->index(); // TEST ANCHOR: shares commit id with facts invalidation
                $table->jsonb('content_blocks');
                $table->boolean('pixel_installed')->default(true); // G9-04 site law
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('funnels')) {
            Schema::create('funnels', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('name');
                $table->jsonb('steps');
                $table->string('short_slug')->unique();
                $table->jsonb('device_routing')->nullable(); // G6-11
                $table->unsignedInteger('click_cap')->nullable(); // G19-07
                $table->unsignedInteger('clicks_count')->default(0);
                $table->timestamp('expires_at')->nullable(); // G16-07
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('site_forks')) {
            Schema::create('site_forks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('forked_template_id'); // TEST ANCHOR: string template id, NO foreign key
                $table->string('fork_commit_hash');
                $table->timestamp('forked_at')->useCurrent();
            });
        }

        $tables = ['pages', 'page_versions', 'funnels', 'site_forks'];

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
        Schema::dropIfExists('site_forks');
        Schema::dropIfExists('funnels');
        Schema::dropIfExists('page_versions');
        Schema::dropIfExists('pages');
    }
};

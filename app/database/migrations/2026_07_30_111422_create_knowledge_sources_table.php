<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the Business Brain was built from (DATA-MODEL §5.9).
 *
 * checksum and version exist so a re-crawl can tell 'unchanged' from 'changed'
 * without re-embedding, which is the difference between a cheap nightly refresh
 * and an expensive one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type');
            $table->text('url')->nullable();
            $table->text('file_path')->nullable();
            $table->string('title')->nullable();

            $table->string('status')->default('pending');
            $table->timestamp('last_ingested_at')->nullable();
            $table->string('checksum')->nullable();
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['business_id', 'is_active', 'status']);
        });

        DB::statement('ALTER TABLE knowledge_sources ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE knowledge_sources FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON knowledge_sources
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_sources');
    }
};

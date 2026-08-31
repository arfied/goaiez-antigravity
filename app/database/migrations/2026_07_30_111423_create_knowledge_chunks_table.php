<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The embedded knowledge the bot answers from (DATA-MODEL §5.9).
 *
 * **This is the sharpest edge in the design** (docs/BUILD-PLAN.md §4.2).
 *
 * Laravel 13 ships `Blueprint::vector()` and `vectorIndex()` but **no similarity
 * search**, so every distance query against this table is raw SQL — and raw SQL
 * passes through no global scope. `ORDER BY embedding <=> ?` without a
 * `business_id` predicate searches every tenant's embeddings and hands another
 * business's document back as bot context, which is both a data breach and a
 * bot confidently citing a competitor.
 *
 * Two things stand in the way, and both are needed:
 *
 *   1. The RLS policy below, which raw SQL cannot bypass.
 *   2. A build-failing test asserting no vector query omits the predicate
 *      (`29` §2.4, decision 139), written before this table existed.
 *
 * The policy is the one that actually holds if someone forgets. The test exists
 * because this is the feature most likely to be written by someone reasoning
 * about relevance rather than isolation, and a query that depends on RLS alone
 * is one `SET app.business_id` mistake from returning the wrong tenant's rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('knowledge_sources')->cascadeOnDelete();

            $table->text('content');
            $table->integer('token_count')->nullable();

            // 1536 dimensions per DATA-MODEL. Changing this later means
            // re-embedding every chunk, so it is fixed at the schema.
            $table->vector('embedding', 1536)->nullable();

            $table->jsonb('metadata')->default('{}');

            $table->timestamp('created_at')->nullable();

            // business_id first: the leftmost prefix serves "all chunks for this
            // business", which is the filter every similarity query must carry.
            $table->index(['business_id', 'source_id']);

            // HNSW with vector_cosine_ops, per the spec's commented-out DDL.
            // Laravel's vectorIndex() compiles to exactly that on PostgreSQL.
            $table->vectorIndex('embedding');
        });

        DB::statement('ALTER TABLE knowledge_chunks ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE knowledge_chunks FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON knowledge_chunks
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};

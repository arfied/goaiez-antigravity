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
        if (! Schema::hasTable('knowledge_chunks')) {
            Schema::create('knowledge_chunks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('document_id')->nullable()->index();
                $table->string('title');
                $table->text('chunk_text');
                $table->jsonb('embedding_vector')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('retrieval_cache')) {
            Schema::create('retrieval_cache', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('query_hash')->index();
                $table->string('query_text');
                $table->jsonb('result_chunk_ids');
                $table->timestamps();
            });
        }

        $tables = ['knowledge_chunks', 'retrieval_cache'];

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
        Schema::dropIfExists('retrieval_cache');
        Schema::dropIfExists('knowledge_chunks');
    }
};

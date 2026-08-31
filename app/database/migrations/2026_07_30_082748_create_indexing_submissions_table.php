<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * URLs submitted to search engines for indexing (DATA-MODEL §5.11), with the
 * engine's response kept for debugging quota and rejection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indexing_submissions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->text('url');

            // google|bing
            $table->string('engine');

            // indexing_api|sitemap|manual
            $table->string('method')->nullable();

            $table->string('status')->nullable();

            $table->jsonb('response')->nullable();

            // The datum — this table has no other timestamps.
            $table->timestamp('submitted_at')->nullable();

            $table->index('business_id');
        });

        DB::statement('ALTER TABLE indexing_submissions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE indexing_submissions FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON indexing_submissions
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('indexing_submissions');
    }
};

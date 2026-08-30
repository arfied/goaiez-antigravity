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
        if (! Schema::hasTable('documents')) {
            Schema::create('documents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('title');
                $table->string('mime_type')->default('application/pdf');
                $table->string('sha256_hash')->index(); // SHA-256 at ingest (G2-30)
                $table->string('status')->default('ingested'); // ingested, reviewed, confirmed
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('document_versions')) {
            Schema::create('document_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
                $table->unsignedInteger('version_number')->default(1);
                $table->string('file_path')->nullable();
                $table->string('sha256_hash');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('extraction_runs')) {
            Schema::create('extraction_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
                $table->string('run_status')->default('completed'); // completed, failed
                $table->unsignedInteger('extracted_facts_count')->default(0);
                $table->string('error_message')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['documents', 'document_versions', 'extraction_runs'];

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
        Schema::dropIfExists('extraction_runs');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
    }
};

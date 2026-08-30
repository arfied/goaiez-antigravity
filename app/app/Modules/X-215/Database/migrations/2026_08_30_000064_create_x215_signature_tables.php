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
        if (! Schema::hasTable('signable_documents')) {
            Schema::create('signable_documents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('document_uuid')->index();
                $table->string('title');
                $table->text('content_body');
                $table->string('content_hash'); // SHA-256 bound hash (TEST ANCHOR)
                $table->unsignedInteger('version')->default(1);
                $table->string('status')->default('sent'); // draft, sent, signed, voided
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('signature_requests')) {
            Schema::create('signature_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('document_id')->constrained('signable_documents')->cascadeOnDelete();
                $table->string('signer_email');
                $table->string('signer_name');
                $table->string('bound_hash'); // Bound hash at send time (TEST ANCHOR)
                $table->text('signature_data')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->string('status')->default('pending'); // pending, signed, voided
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('document_comments')) {
            Schema::create('document_comments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('document_id')->constrained('signable_documents')->cascadeOnDelete();
                $table->string('author_email');
                $table->text('comment_text');
                $table->timestamps();
            });
        }

        $tables = ['signable_documents', 'signature_requests', 'document_comments'];

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
        Schema::dropIfExists('document_comments');
        Schema::dropIfExists('signature_requests');
        Schema::dropIfExists('signable_documents');
    }
};

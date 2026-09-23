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
        if (! Schema::hasTable('staff_documents')) {
            Schema::create('staff_documents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('staff_user_id')->constrained('staff_users')->cascadeOnDelete();
                $table->string('original_filename');
                $table->string('mime_type');
                $table->unsignedBigInteger('size_bytes');
                $table->string('sha256', 64);
                $table->string('storage_path');
                $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        DB::statement('ALTER TABLE staff_documents ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE staff_documents FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON staff_documents');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON staff_documents
                USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_documents');
    }
};

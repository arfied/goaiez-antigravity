<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Generated QR posters, cards, and PDFs per location (DATA-MODEL §5.11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_materials', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            // qr_poster|table_tent|business_card|…
            $table->string('type');

            $table->string('qr_code_url')->nullable();
            $table->string('pdf_url')->nullable();
            $table->string('call_to_action')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index('business_id');
        });

        DB::statement('ALTER TABLE print_materials ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE print_materials FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON print_materials
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('print_materials');
    }
};

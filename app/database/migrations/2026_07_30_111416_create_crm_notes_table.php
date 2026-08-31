<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Free-text notes on a customer (DATA-MODEL §5.5).
 *
 * business_id is added per decision 176: the spec keys this by customer_id
 * alone, and the RLS policy compares business_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->text('body');

            $table->timestamp('created_at')->nullable();

            $table->index(['business_id', 'customer_id']);
        });

        DB::statement('ALTER TABLE crm_notes ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE crm_notes FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON crm_notes
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_notes');
    }
};

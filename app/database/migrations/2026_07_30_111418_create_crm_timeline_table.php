<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every event against a customer, in one stream (DATA-MODEL §5.5).
 *
 * Singular by spec — 'crm_timeline', not 'crm_timelines'. The model sets
 * $table explicitly for the same reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_timeline', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->string('event_type');
            $table->string('source')->nullable();
            $table->string('summary')->nullable();
            $table->jsonb('metadata')->default('{}');

            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('created_at')->nullable();

            // The timeline is always read newest-first for one customer.
            $table->index(['business_id', 'customer_id', 'occurred_at']);
        });

        DB::statement('ALTER TABLE crm_timeline ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE crm_timeline FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON crm_timeline
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_timeline');
    }
};

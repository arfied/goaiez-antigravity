<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant, per-channel suppression (DATA-MODEL §5.6). The send path checks
 * this before anything is queued; DNC, Reassigned Numbers, and litigator
 * suppression data live in tables, never hardcoded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppression_list', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->string('channel');

            // The suppressed address (phone or email, per channel).
            $table->string('identifier');

            $table->string('reason')->nullable();

            $table->timestamp('created_at')->nullable();

            // Idempotency for the suppression writer, scoped per tenant: the
            // same person opting out of two tenants is two rows.
            $table->unique(['business_id', 'channel', 'identifier']);
        });

        DB::statement('ALTER TABLE suppression_list ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE suppression_list FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON suppression_list
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('suppression_list');
    }
};

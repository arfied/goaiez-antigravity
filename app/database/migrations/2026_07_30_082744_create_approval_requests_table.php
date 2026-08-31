<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A CONFIRM prompt sent to the owner (DATA-MODEL §5.12), normally by SMS —
 * the owner approves by text and never logs in.
 *
 * CONFIRM is reserved for exactly three things (`29` §2 rule 37): GBP
 * core-field changes, anything that spends money, and the first send of a
 * new campaign type. auto_action_on_expiry records what an unanswered
 * request does; for gated actions expiry never means "proceed".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // gbp_core_fields|spend|new_campaign_type|…
            $table->string('type');

            // What is being approved, by reference. Unconstrained morphs on
            // purpose: the entity may live in any table.
            $table->nullableMorphs('entity');

            $table->string('channel')->default('sms');

            // The message shown to the owner. Outcome language; no secrets.
            $table->text('message')->nullable();

            $table->jsonb('options')->nullable();

            $table->string('status')->default('pending');

            $table->timestamp('responded_at')->nullable();
            $table->string('response')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->string('auto_action_on_expiry')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['business_id', 'status']);
        });

        DB::statement('ALTER TABLE approval_requests ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE approval_requests FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON approval_requests
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};

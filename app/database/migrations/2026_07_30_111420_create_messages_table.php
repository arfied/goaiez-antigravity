<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Individual messages within a conversation (DATA-MODEL §5.9).
 *
 * business_id added per decision 176 — the spec keys this by conversation_id
 * alone, and a policy cannot join.
 *
 * Message bodies are the most sensitive free text in the system: for a PHI
 * tenant they are not visible to CS agents at all (`29` §19.5), and they never
 * reach L3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();

            $table->string('direction');
            $table->string('sender_type');
            $table->string('sender_id')->nullable();
            $table->text('body')->nullable();
            $table->jsonb('attachments')->default('[]');
            $table->string('provider_msg_id')->nullable();

            // Which model produced this, when one did. Never a hardcoded name
            // elsewhere — models are configuration (`29` §2 rule 45).
            $table->string('ai_model')->nullable();
            $table->decimal('ai_confidence', 4, 3)->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['business_id', 'conversation_id', 'created_at']);
            $table->index(['business_id', 'provider_msg_id']);
        });

        DB::statement('ALTER TABLE messages ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE messages FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON messages
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

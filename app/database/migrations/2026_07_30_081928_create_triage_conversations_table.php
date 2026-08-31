<?php

declare(strict_types=1);

use App\Enums\OutreachChannel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The recovery conversation a below-threshold review opens
 * (DATA-MODEL §5.7). Below-threshold customers always reach triage and a
 * recovery path (decision 114) — this table is that path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('triage_conversations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();

            // Nullable: an anonymous first-party review has no matched customer.
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('channel')->default(OutreachChannel::Sms->value);

            // open|resolved|escalated|no_response|closed
            $table->string('status')->default('open');

            $table->jsonb('transcript')->default('[]');

            $table->text('resolution')->nullable();
            $table->timestamp('owner_notified_at')->nullable();

            // The human-takeover flag: once the owner steps in, AI stays out.
            $table->boolean('ai_paused')->default(false);

            $table->timestamps();

            $table->index(['business_id', 'status']);
        });

        DB::statement('ALTER TABLE triage_conversations ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE triage_conversations FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON triage_conversations
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('triage_conversations');
    }
};

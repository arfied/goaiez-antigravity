<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Support-desk threads across every channel (DATA-MODEL §5.9).
 *
 * consent_logged_at is not decoration: CIPA requires notice before chat capture,
 * and `29` §2 rule 22 makes 'no capture before consent' build-failing. A row
 * without it has not been cleared to store message bodies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('channel');
            $table->string('subject')->nullable();
            $table->string('status')->default('new');
            $table->string('priority')->default('normal');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('is_bot_handled')->default(true);
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->smallInteger('csat_score')->nullable();

            $table->jsonb('tags')->default('[]');
            $table->timestamp('consent_logged_at')->nullable();

            $table->timestamps();

            $table->index(['business_id', 'status', 'sla_due_at']);
        });

        DB::statement('ALTER TABLE conversations ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE conversations FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON conversations
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};

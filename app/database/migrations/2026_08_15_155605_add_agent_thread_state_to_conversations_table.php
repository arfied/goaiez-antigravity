<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the assistant stands on a thread — T176 §2.3 rails 3 and 4, patch P3.
 *
 * ⚠️ **COLUMNS ON `conversations` RATHER THAN A SECOND TABLE, AND THE LATCH IS
 * WHY.** Rail 4 admits no exceptions, so the question *"may the agent speak on
 * this thread"* has to be answerable and writable under one row lock. A join
 * table would make the state a row that can be missing, and a missing row has to
 * be interpreted — the natural interpretation of "no state" being the one that
 * lets the agent speak, which is the failure the latch exists to prevent.
 *
 * ⚠️ **NO RLS STATEMENTS HERE, AND THAT IS NOT AN OMISSION.** `conversations`
 * already carries `ENABLE` + `FORCE ROW LEVEL SECURITY` and its
 * `tenant_isolation` policy from
 * `2026_07_30_111419_create_conversations_table.php`. That policy is
 * `business_id = current_setting('app.business_id')`, which covers every column
 * on the row including these; re-declaring it here would be a second source of
 * truth for one rule.
 *
 * ⛔ **`agent_status` IS A STRING, NOT A POSTGRES ENUM** — CLAUDE.md's standing
 * rule. It casts to `App\Enums\AgentThreadStatus`, whose six cases are exactly
 * the churn that rule names.
 *
 * ⚠️ **`agent_turn_cap` IS NULLABLE ON PURPOSE.** Rail 3's cap is registry-keyed
 * (`assistant.turn_cap`) and a thread already in flight is judged by the cap it
 * started under — `App\Services\Agent\ThreadState`'s own docblock. NULL
 * means "no stretch has started on this thread yet", and the reader falls back
 * to today's registry figure. A schema default of 12 would freeze the seed into
 * every row ever written and make moving the key a no-op for every existing
 * thread, which is 1420's shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('agent_status')->default('unhandled');
            $table->unsignedInteger('agent_turns_used')->default(0);
            $table->unsignedSmallInteger('agent_turn_cap')->nullable();
            $table->timestamp('agent_latched_at')->nullable();

            // Who took over. Recorded because "a human replied" is an assertion
            // the audit log has to be able to name somebody for, and because the
            // Inbox (P18) says which teammate is answering. nullOnDelete rather
            // than cascade: a departed teammate must not delete the thread they
            // answered on.
            $table->foreignId('agent_latched_by')->nullable()->constrained('users')->nullOnDelete();

            // The Inbox's needs-attention query is "which of this tenant's
            // threads is the assistant not handling", which is exactly this.
            $table->index(['business_id', 'agent_status']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex(['business_id', 'agent_status']);
            $table->dropConstrainedForeignId('agent_latched_by');
            $table->dropColumn([
                'agent_status',
                'agent_turns_used',
                'agent_turn_cap',
                'agent_latched_at',
            ]);
        });
    }
};

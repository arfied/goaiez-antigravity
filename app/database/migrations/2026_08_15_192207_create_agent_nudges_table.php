<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The one follow-up a silent thread is owed — T176 §2.2 row 14, patch P11.
 *
 * *"Quote/fee/booking link sent, no reply → ONE polite nudge inside 24h,
 * quiet-hours-respecting (not time-critical), then stop."*
 *
 * ⛔ **ONE ROW PER THREAD, EVER, AND THE UNIQUE INDEX IS THE PROMISE.** "Then
 * stop" is the whole of the product requirement, and a `SELECT`-then-`INSERT` in
 * a service is not what makes it true when two links go out on one thread inside
 * the same second. `sent_at` and `cancelled_at` are set on this same row rather
 * than a second one being written, so the row is both the schedule and the
 * receipt.
 *
 * ⚠️ **`armed_at_turns` IS THE "NO REPLY" TEST AND IT IS A PROXY, WHICH IS SAID
 * OUT LOUD.** This schema has no observable "the customer replied": `messages`
 * has no writer in `app/` at all, and `inbound_messages` is the platform-scoped
 * STOP/HELP keyword register with no business, no body and a hashed identifier.
 * What *is* observable is `conversations.agent_turns_used`, which
 * `AgentThreadStates::recordTurn()` moves whenever the assistant answers
 * something — so a counter that has moved since arming means another inbound
 * arrived and was answered. The gap is recorded in `AgentNudges::wasAnswered()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_nudges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            // ⛔ **ONE PER THREAD — see above.**
            $table->foreignId('conversation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->timestamp('armed_at');

            // The thread's turn count when the link went out. See the class note.
            $table->unsignedInteger('armed_at_turns')->default(0);

            // When the sweep may first consider it, and the moment after which it
            // is abandoned rather than sent — §2.2's *"inside 24h"* is a
            // constraint on the nudge being timely, not merely on it being sent.
            $table->timestamp('due_at');
            $table->timestamp('expires_at');

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // ⚠️ **A REASON, NEVER A MESSAGE.** Why we did not follow up is an
            // operator question; what anybody said is the thread's.
            $table->string('cancel_reason')->nullable();

            $table->timestamps();

            // The sweep's own query: everything pending, oldest due first.
            $table->index(['business_id', 'sent_at', 'cancelled_at', 'due_at']);
        });

        DB::statement('ALTER TABLE agent_nudges ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE agent_nudges FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON agent_nudges
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_nudges');
    }
};

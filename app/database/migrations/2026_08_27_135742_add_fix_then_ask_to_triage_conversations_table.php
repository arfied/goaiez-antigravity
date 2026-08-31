<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The wait, the check-in, and the answer — T546 §37.3(1), wave 38 lane C
 * (10590–10609).
 *
 * *"1–3★ … ticket … resolved … 7 days later: 'Did we get that sorted?' … yes
 * … NOW ask for the review."* This is the state a resolved recovery
 * conversation needs to run that loop, on `triage_conversations` rather than a
 * new table — it is one more fact about the same conversation, and a second
 * table would need its own tenant guard and its own chokepoint argument for a
 * fact `ReviewRouter` already owns.
 *
 * `resolved_at`: WHEN the conversation most recently reached `Resolved`,
 * distinct from `updated_at` — which moves on every edit to `resolution`,
 * every `ai_paused` toggle, and every re-open. The scheduler needs the delay to
 * run from the moment resolution happened, not from the moment anybody last
 * touched the row. Written by `ReviewRouter::recordTriageOutcome()`, the one
 * writer of `status`, every time `$to === TriageStatus::Resolved` — so it
 * moves forward if a conversation is reopened and resolved again, which is
 * correct: a second fix earns its own delay.
 *
 * `fix_then_ask_offered_at`: when the check-in message was actually sent.
 * Null means never offered, which is what the sweep and the sender's own
 * duplicate guard both read.
 *
 * `fix_then_ask_response` / `fix_then_ask_responded_at`: the customer's own
 * answer and when it arrived. Nullable, because most check-ins go unanswered —
 * silence is the common case, per `CLAUDE.md`'s rule that a review invite is
 * never asked for on an inferred or assumed "yes". `fix_then_ask_response` is a
 * `string` cast to `App\Enums\FixThenAskResponse` — never a database enum, this
 * codebase's standing rule.
 *
 * No RLS statements: `triage_conversations` already carries `ENABLE` + `FORCE`
 * and its `tenant_isolation` policy from the create migration, and a policy is
 * a property of the table rather than of a column (the same note the outreach
 * draft columns carried).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('triage_conversations', function (Blueprint $table): void {
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('fix_then_ask_offered_at')->nullable();
            $table->string('fix_then_ask_response')->nullable();
            $table->timestamp('fix_then_ask_responded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('triage_conversations', function (Blueprint $table): void {
            $table->dropColumn([
                'resolved_at',
                'fix_then_ask_offered_at',
                'fix_then_ask_response',
                'fix_then_ask_responded_at',
            ]);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Somewhere for the recovery message the owner has to send by hand (T176 P15).
 *
 * ⚠️ **NOT `outreach_messages`, AND THE REFUSAL IS THE WHOLE POINT** (4353).
 * That table is the record of a message that *went*: `OutreachStatus` has **no
 * draft state**, and `Queued` is its entry state — so a drafted win-back message
 * parked there is indistinguishable from one waiting to go out, and is one
 * scheduler away from reaching somebody's unhappy customer with nobody having
 * approved it. P15's posture is explicitly draft-and-approve, unchanged.
 * Nothing in `app/` sends from `triage_conversations`, so a draft here cannot
 * become a send by accident.
 *
 * ⚠️ **THE ORIGINAL WORDING SAID "THE SEND PATH SWEEPS QUEUED ROWS" AND NO SUCH
 * SWEEP EXISTS** (4471). The refusal is unchanged and still right — the argument
 * is the *absence* of a draft state, not the presence of a sweeper — but naming
 * a mechanism that is not there invites the next reader to go and build one on
 * the belief that it already exists, or to look for it and conclude this
 * paragraph is stale in some other way. `Queued` today is written and read by
 * nothing that sweeps; what makes parking a draft there dangerous is that the
 * day something does sweep it, this row is already in the queue.
 *
 * ⚠️ **NOT `transcript` EITHER.** That column is TRIAGE-01's customer-facing
 * turn log and nothing appends to it (942); writing our draft into it would
 * render as *"this is what was said"* when nothing has been said to anybody.
 * And not `resolution`, which is the owner's own note about what they did.
 *
 * `outreach_draft_fallback_reason` is null when the words came from the model
 * and names the cause when the platform-owned template won — the same
 * distinction `ReplyDraft` draws for the Google path, because "the model
 * declined" and "Anthropic returned a 500" must not be filed as one answer.
 *
 * No RLS statements: `triage_conversations` already carries `ENABLE` + `FORCE`
 * and its `tenant_isolation` policy from the create migration, and a policy is
 * a property of the table rather than of a column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('triage_conversations', function (Blueprint $table): void {
            $table->text('outreach_draft')->nullable();
            $table->timestamp('outreach_draft_at')->nullable();
            $table->string('outreach_draft_fallback_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('triage_conversations', function (Blueprint $table): void {
            $table->dropColumn([
                'outreach_draft',
                'outreach_draft_at',
                'outreach_draft_fallback_reason',
            ]);
        });
    }
};

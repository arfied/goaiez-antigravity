<?php

declare(strict_types=1);

use App\Enums\ImpersonationMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Support inside a customer's account, bounded and recorded — `28` §9.4,
 * `DATA-MODEL` Parts 9–10.
 *
 * ⚠️ NOT TENANT-OWNED, AND THE REASON IS CIRCULAR RESOLUTION RATHER THAN
 * OWNERSHIP. The row carries a `business_id` and is emphatically *about* one
 * tenant, so the trait looks right. It cannot go on: this table is what
 * *establishes* the tenant for an impersonated request, and it is read by a
 * platform agent who has no tenant of their own — `ResolveTenant` resolves
 * nothing for a `support_lead`, correctly, because they own no business. A
 * global scope calling `Tenancy::idOrFail()` here would throw on the one query
 * whose job is to answer "which tenant should this request be in".
 *
 * The same shape as `feedback_pages` (decision 318) and `plugins` (400), and it
 * takes the same answer they did rather than an exemption: **RLS is enabled and
 * FORCEd, with a policy naming the actual reader.** The reader here is not a
 * tenant and not the public — it is us — so the policy is written against the
 * migration role's own predicate rather than against `app.business_id`, and the
 * thing that actually protects the table is the admin gate above it. That is
 * `legal_documents`' posture (417–420), and it is stated here because a reader
 * finding `USING (true)` on a table full of support activity should find the
 * reason next to it and not have to go looking.
 *
 * ⚠️ APPEND-MOSTLY, NOT APPEND-ONLY, and the difference is deliberate. A
 * session is written once at start and then updated exactly three ways — ended,
 * page_views incremented, writes incremented. It is evidence, so it carries no
 * `updated_at` and nothing may edit `agent_id`, `business_id`, `mode`, `reason`
 * or `ticket_id` after insert; a trigger enforces that, because the whole value
 * of this row in a dispute is that the reason recorded is the reason given at
 * the time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonation_sessions', function (Blueprint $table): void {
            $table->id();

            // Ours. Restricted rather than cascading: deleting the staff member
            // who opened a session must not delete the record that they did.
            $table->foreignId('agent_id')->constrained('users')->restrictOnDelete();

            // ⚠️ Theirs, and RESTRICTED rather than cascading — which is the
            // opposite of what every other business_id in this schema does, so
            // it needs its reason on the line. A cascade here would have the
            // deletion of a business quietly destroy the record of our own
            // staff having been inside it, and that record is not the tenant's
            // to take with them: it is evidence about us. The delete trigger
            // below would refuse the cascade anyway, so the choice is only
            // between failing at the foreign key, which names this table, and
            // failing inside a trigger, which names a function.
            //
            // Nothing deletes a business today and ImpersonationCapability
            // ::DeleteBusiness says nothing ever will through this door. When a
            // real deletion path is built it has to decide what happens to this
            // history rather than inherit an answer from a default.
            $table->foreignId('business_id')->constrained()->restrictOnDelete();

            // A string cast to ImpersonationMode, never a database enum
            // (CLAUDE.md). `DATA-MODEL` writes it as `mode imp_mode`.
            $table->string('mode');

            // ⚠️ NOT NULLABLE, INCLUDING FOR VIEW MODE. `28` §9.4 asks for a
            // typed reason only on act-as, and this column is required on both.
            // A view-only session reads everything a customer has ever written
            // — reviews, feedback, phone numbers — and "why were you in this
            // account" is a question with an answer or it is a breach. The
            // difference `28` intends survives in the *length* the service
            // demands, not in whether one is asked for.
            $table->string('reason', 500);

            // Act-as only. Free text rather than a foreign key: `support_tickets`
            // does not exist yet (row 7), and a nullable key to a missing table
            // is not something to invent — decision 274's rule, that a reference
            // you cannot dereference should be stored as the provenance it is.
            $table->string('ticket_ref')->nullable();

            $table->timestamp('started_at');

            // The clock. Read on every request rather than swept, so a session
            // is dead the instant it lapses and not whenever a job next runs.
            $table->timestamp('expires_at')->index();

            $table->timestamp('ended_at')->nullable();

            // ended | expired | agent_logout | revoked — App\Enums\ImpersonationEnding.
            $table->string('ended_reason')->nullable();

            // `28` §9.4: "every impersonated page view (view-only included) is
            // logged". Counters here, detail in audit_log — a row per page view
            // in this table would bury the sessions among their own traffic.
            $table->unsignedInteger('page_views')->default(0);
            $table->unsignedInteger('writes')->default(0);

            $table->timestamp('created_at')->nullable();

            // `DATA-MODEL`'s named index for this table.
            $table->index(['business_id', 'started_at']);
        });

        // `DATA-MODEL` asks for (business_id, started_at DESC); Blueprint has no
        // way to say DESC, and the direction matters on the read path this index
        // exists for — "the most recent sessions on this account".
        DB::statement('DROP INDEX impersonation_sessions_business_id_started_at_index');
        DB::statement(<<<'SQL'
            CREATE INDEX impersonation_sessions_business_id_started_at_index
                ON impersonation_sessions (business_id, started_at DESC)
        SQL);

        // The agent's own view: "what am I currently in". Partial, because a
        // live session is a vanishing fraction of the table forever.
        DB::statement(<<<'SQL'
            CREATE INDEX impersonation_sessions_live_index
                ON impersonation_sessions (agent_id)
                WHERE ended_at IS NULL
        SQL);

        // ⚠️ ONE LIVE SESSION PER BUSINESS (`28` §9.4's concurrent limit),
        // enforced here rather than only in the service. Two agents inside one
        // account at once makes the audit trail ambiguous about who did what,
        // and the check-then-insert in PHP is a race — two support agents
        // clicking within the same second is not a hypothetical on a busy desk.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX impersonation_sessions_one_live_per_business
                ON impersonation_sessions (business_id)
                WHERE ended_at IS NULL
        SQL);

        // Mode is closed at the database too — slice B's three-layer thesis
        // (303–316), on a column whose wrong value grants writes.
        $modes = collect(ImpersonationMode::cases())
            ->map(fn (ImpersonationMode $mode): string => "'".$mode->value."'")
            ->implode(', ');

        DB::statement(<<<SQL
            ALTER TABLE impersonation_sessions
                ADD CONSTRAINT impersonation_sessions_mode_is_known
                CHECK (mode IN ({$modes}))
        SQL);

        // A reason that is present but empty is the same as no reason, and
        // `''` is what a form posts when a required field is bypassed.
        DB::statement(<<<'SQL'
            ALTER TABLE impersonation_sessions
                ADD CONSTRAINT impersonation_sessions_reason_is_given
                CHECK (length(btrim(reason)) >= 10)
        SQL);

        // `28` §9.4 requires a linked ticket on act-as and not on view.
        DB::statement(<<<'SQL'
            ALTER TABLE impersonation_sessions
                ADD CONSTRAINT impersonation_sessions_act_carries_a_ticket
                CHECK (mode <> 'act' OR ticket_ref IS NOT NULL)
        SQL);

        // An ending has a reason and a reason has an ending. Either alone is a
        // half-written row, and the half that reads "ended_at IS NULL" is a
        // session this table would report as still live.
        DB::statement(<<<'SQL'
            ALTER TABLE impersonation_sessions
                ADD CONSTRAINT impersonation_sessions_ending_is_complete
                CHECK ((ended_at IS NULL) = (ended_reason IS NULL))
        SQL);

        // ⚠️ THE ONLY OTHER TRIGGER IN THIS SCHEMA FREEZES A PUBLISHED LEGAL
        // DOCUMENT (417–420), and this is the same argument. The value of a
        // reason is that it was given *before* the session, by somebody who did
        // not yet know what they would find. A reason editable afterwards is a
        // reason written afterwards, and nothing in the row would show it.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION impersonation_sessions_freeze_terms()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.agent_id     IS DISTINCT FROM OLD.agent_id
                OR NEW.business_id  IS DISTINCT FROM OLD.business_id
                OR NEW.mode         IS DISTINCT FROM OLD.mode
                OR NEW.reason       IS DISTINCT FROM OLD.reason
                OR NEW.ticket_ref   IS DISTINCT FROM OLD.ticket_ref
                OR NEW.started_at   IS DISTINCT FROM OLD.started_at
                THEN
                    RAISE EXCEPTION
                        'impersonation_sessions: the terms of a session are fixed when it starts';
                END IF;

                -- An ended session is finished. Re-opening one by clearing
                -- ended_at would slip past the live-session unique index and
                -- reuse a reason given for something else.
                IF OLD.ended_at IS NOT NULL AND NEW.ended_at IS DISTINCT FROM OLD.ended_at THEN
                    RAISE EXCEPTION
                        'impersonation_sessions: a session that has ended cannot be reopened';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER impersonation_sessions_freeze_terms
                BEFORE UPDATE ON impersonation_sessions
                FOR EACH ROW EXECUTE FUNCTION impersonation_sessions_freeze_terms()
        SQL);

        // Rows may never be deleted. `28` §0 rule 9: internal tools are *more*
        // audited than normal access, never less, and a support session that
        // can be deleted by the person who opened it is not a record.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION impersonation_sessions_refuse_delete()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION
                    'impersonation_sessions: support sessions are a permanent record';
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER impersonation_sessions_refuse_delete
                BEFORE DELETE ON impersonation_sessions
                FOR EACH ROW EXECUTE FUNCTION impersonation_sessions_refuse_delete()
        SQL);

        // RLS on, FORCEd, and a policy — the posture of every table in this
        // schema, including the un-tenanted ones. See the class docblock for
        // why the predicate is not `app.business_id`: the reader has no tenant,
        // by construction, and what bounds this table is the admin gate.
        DB::statement('ALTER TABLE impersonation_sessions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE impersonation_sessions FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY platform_staff_only ON impersonation_sessions
                FOR ALL USING (true) WITH CHECK (true)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_sessions');

        DB::statement('DROP FUNCTION IF EXISTS impersonation_sessions_freeze_terms()');
        DB::statement('DROP FUNCTION IF EXISTS impersonation_sessions_refuse_delete()');
    }
};

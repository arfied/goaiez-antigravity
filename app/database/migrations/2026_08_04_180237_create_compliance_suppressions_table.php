<?php

declare(strict_types=1);

use App\Enums\ComplianceList;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The scrubbing registers `29` §2 rule 11 requires in a table rather than in
 * code: DNC, reassigned numbers, and known litigators.
 *
 * ⚠️ "AS A TABLE, NEVER HARDCODED" IS THE REQUIREMENT ITSELF, not a note about
 * how to implement one. Rule 11's own justification is that "Florida and
 * Washington are stricter than federal **and the list moves**" — a register
 * compiled into a deploy is one that is wrong between deploys, and the wrongness
 * is a message to somebody who is on a list we were told about and did not read.
 *
 * ⚠️ NOT TENANT-OWNED, AND NO RLS. Identical reasoning to `opt_outs`: these
 * registers are facts about a phone number, not about a business. A tenant-owned
 * copy would mean the same federal DNC list imported once per business, each
 * copy able to be stale independently, and a lookup that needed a resolved
 * tenant before it could refuse. The model joins the `ArchitectureTest` scope
 * allowlist with its reasoning there.
 *
 * ⚠️ `value_hash`, NEVER THE IDENTIFIER — and here the argument is stronger than
 * it was for `opt_outs`. A plaintext federal DNC extract is a national register
 * of phone numbers belonging to people who have explicitly said they do not want
 * to be contacted; a plaintext litigator list is a list of named individuals and
 * their numbers. Neither may sit readable in an application database.
 * `App\Support\Identifier::hash()` is the only writer of these values and
 * normalises before hashing, because a hash matches only exactly and an
 * un-normalised hash silently matches nothing — which is the whole exposure.
 *
 * ⚠️ THE TABLE SHIPS EMPTY AND THAT IS VISIBLE IN THE BEHAVIOUR, not only in a
 * comment. None of these registers is free: federal DNC is a subscription, the
 * Reassigned Numbers Database is an FCC-designated administrator, litigator
 * lists are commercial. Until one has been loaded, `ConsentService` refuses
 * every *marketing* send — `SuppressionRegistry::isLoaded()`. Transactional
 * sends are unaffected, which is every message this product sends today (`24`
 * §3.3), so the fail-closed direction costs nothing and cannot be forgotten.
 *
 * ⛔ AND `isLoaded()` IS THE ONE ANSWER A KEY ROTATION LEAVES INTACT, WHICH
 * INVERTS THE PARAGRAPH ABOVE. That gate counts rows by `list` and
 * `identifier_type` and never touches `value_hash`, while
 * `SuppressionRegistry::refusalsFor()` compares this column against a freshly
 * computed `Identifier::hash()`. Rotate `APP_KEY` and every stored row becomes
 * unmatchable at once — so the registers refuse nobody, the fail-closed
 * marketing gate goes on passing because the rows are still counted, and a
 * loaded register and a disarmed one are indistinguishable from every screen
 * and every query in this application. `sending_health_windows`' shape with a
 * compliance register in it (`CLAUDE.md`, the recurring failure shapes).
 *
 * ✅ AND THAT IS THE ACCOUNT OF THE DEFECT RATHER THAN OF TODAY — `isLoaded()`
 * IS CORRECTED (8086, 8192). It is now `isReadable() && missingForMarketing()
 * === []`, so a rotation closes the marketing gate instead of leaving it open.
 * ⚠️ `missingForMarketing()` IS DELIBERATELY UNCHANGED and still counts rows by
 * list and channel: it answers *"what does an operator have to import"*, and
 * importing under a new key writes rows beside inert ones and makes it worse.
 * ⚠️ AND ACCEPTING THE LOSS SUPERSEDES EVERY ROW HERE IN THE SAME TRANSACTION
 * (8186) — otherwise retiring the epoch would restore the gate to *loaded* over
 * rows that can never match again, which is this paragraph's failure rebuilt by
 * the command that resolves it.
 *
 * ⚠️ AND IT REACHES TRANSACTIONAL SENDS, NOT ONLY MARKETING.
 * `ComplianceList::appliesTo()` puts litigator and reassigned-number on both
 * purposes — the litigator refusal ahead of the `isLoaded()` gate and the
 * reassignment one after the consent record is found, where the date can be
 * compared. Neither is behind the marketing gate, so a rotation disarms both for
 * every message this product sends.
 *
 * ✅ THE ONE RECOVERABLE HALF, AND `source_reference` IS WHY. Each row names the
 * batch, file or dated extract it came from, so a register can be re-imported
 * from its source — where `opt_outs` cannot be re-derived from anything, because
 * no clear copy of a carrier STOP exists in the schema. **Re-import every
 * register after a rotation, and do not trust `isLoaded()` to tell you whether
 * you have.** `.claude/skills/deploying/` carries the operator's account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_suppressions', function (Blueprint $table): void {
            $table->id();

            // A string cast to ComplianceList, never a database enum (CLAUDE.md).
            $table->string('list');

            // sms | email | whatsapp. Per channel because a DNC registry governs
            // phone numbers and says nothing about an address — decision 286's
            // lesson, that one flag cannot mean "suppressed on SMS, reachable on
            // email".
            $table->string('identifier_type');

            $table->string('value_hash', 64);

            // Two characters, and only for state_dnc. Uppercased by the service.
            $table->string('state', 2)->nullable();

            /*
             * ⚠️ THE DATE A REASSIGNMENT TOOK EFFECT, AND THE REASON THIS TABLE
             * IS NOT A BLOCKLIST. A reassignment entry does not mean "never
             * message this number" — it means the number changed hands on a
             * date, so consent captured *before* that date was given by
             * somebody else and consent captured after it was not. Storing the
             * row without the date would turn a dated invalidation into a
             * permanent block on a number whose current subscriber may have
             * legitimately consented.
             */
            $table->date('effective_from')->nullable();

            // Which import put this row here — a vendor batch id, a file name, a
            // date-stamped extract. Not decorative: "why is this number blocked"
            // is answered by naming the register and the batch, and a register
            // that cannot be traced to its source cannot be corrected.
            $table->string('source_reference')->nullable();

            $table->timestamp('created_at')->nullable();

            /*
             * ⚠️ REMOVAL IS SOFT, AND THAT IS WHAT MAKES THIS TABLE ITS OWN
             * AUDIT RECORD. `29` §2 rule 42 wants every sensitive action in an
             * append-only log, and `audit_log` cannot hold this one: it is
             * tenant-owned with RLS on `business_id`, while a federal register
             * import belongs to no tenant and its only caller is a console
             * command with no tenant resolved. Filing a national DNC load under
             * one arbitrary business would be worse than not filing it.
             *
             * So the row is the record, which is the precedent `LegalDocument`
             * set for the other platform-scoped table in this schema ("this row
             * is the audit record of the act"). A load is evidenced by
             * `source_reference` and `created_at`; a removal would have been
             * evidenced by nothing at all, because a deleted row leaves no
             * trace — and a removal is the direction that *unblocks* somebody,
             * so it is the one worth being able to answer for.
             */
            $table->timestamp('removed_at')->nullable();
            $table->string('removed_by')->nullable();
            $table->string('removed_reason')->nullable();

            // One row per (register, channel, identifier), for all time. A
            // re-import of the same extract is the ordinary case, and a
            // previously-removed identifier arriving again reactivates the same
            // row rather than adding a second — which is why this stays unique
            // across removed rows too.
            $table->unique(
                ['list', 'identifier_type', 'value_hash'],
                'compliance_suppressions_list_type_value_unique'
            );

            // The read path: every register consulted for one hash and channel
            // in a single query. Postgres does not index this for us — decision
            // 314–316's finding, a MySQL habit that does not transfer.
            $table->index(['identifier_type', 'value_hash']);

            // The freshness read: "is any register loaded", which excludes
            // removed rows.
            $table->index(['list', 'removed_at']);
        });

        /*
         * The three-layer rule of slice B (314–316), for constraints whose
         * failure is either a message to somebody on a register we paid to be
         * told about, or a number blocked forever by a row that lost its date.
         *
         * Each of these is also enforced in `ComplianceList` and in
         * `SuppressionRegistry`. The enum stops a bad value reaching the model,
         * the service hands the caller an error they can act on instead of a
         * SQLSTATE, and the CHECK catches the repair script that reached
         * neither.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE compliance_suppressions
                ADD CONSTRAINT compliance_suppressions_reassignment_is_dated
                CHECK (
                    list <> 'reassigned_number' OR effective_from IS NOT NULL
                )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE compliance_suppressions
                ADD CONSTRAINT compliance_suppressions_state_matches_list
                CHECK (
                    (list = 'state_dnc' AND state IS NOT NULL)
                    OR (list <> 'state_dnc' AND state IS NULL)
                )
        SQL);

        // A 64-character hex digest and nothing else. `Identifier::hash()` is
        // hash_hmac('sha256', …), so a value of any other shape reached this
        // column without going through the normaliser — which is the silent
        // failure the whole hashing scheme exists to remove.
        DB::statement(<<<'SQL'
            ALTER TABLE compliance_suppressions
                ADD CONSTRAINT compliance_suppressions_value_hash_is_sha256
                CHECK (value_hash ~ '^[0-9a-f]{64}$')
        SQL);

        $lists = implode(', ', array_map(
            static fn (ComplianceList $list): string => "'".$list->value."'",
            ComplianceList::cases(),
        ));

        DB::statement(<<<SQL
            ALTER TABLE compliance_suppressions
                ADD CONSTRAINT compliance_suppressions_list_is_known
                CHECK (list IN ({$lists}))
        SQL);

        // A removal is a date, an actor and a reason, or it is none of them. A
        // row carrying `removed_at` with no `removed_by` records that somebody
        // unblocked a number and not who — which is the one fact the soft
        // removal exists to preserve.
        DB::statement(<<<'SQL'
            ALTER TABLE compliance_suppressions
                ADD CONSTRAINT compliance_suppressions_removal_is_attributed
                CHECK (
                    (removed_at IS NULL AND removed_by IS NULL AND removed_reason IS NULL)
                    OR (removed_at IS NOT NULL AND removed_by IS NOT NULL AND removed_reason IS NOT NULL)
                )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_suppressions');
    }
};

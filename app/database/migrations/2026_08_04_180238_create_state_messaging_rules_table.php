<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The state mini-TCPA rules table — the last of `29` §2 rule 11's four.
 *
 * ⚠️ "MAINTAIN A STATE RULES TABLE, DO NOT HARDCODE" (`24` §3.4) IS A RULE ABOUT
 * HOW OFTEN THE LAW CHANGES, not about tidiness. Florida's FTSA was amended
 * within two years of passing, Washington's CEMA predates texting and has been
 * read onto it by courts, and Oklahoma copied Florida after Florida had already
 * been narrowed. A statute compiled into a deploy is a statute that is wrong
 * between deploys, and being wrong here is statutory damages per message.
 *
 * ⚠️ NOT TENANT-OWNED AND NO RLS, the same family as `legal_documents`
 * (417–420): these are laws, not a tenant's data. Every business in Florida is
 * bound by the same row, and a tenant-owned copy would let one tenant hold a
 * stale version of a statute. The model joins the `ArchitectureTest` scope
 * allowlist with its reasoning there.
 *
 * ⚠️ NOTHING IN THIS SCHEMA KNOWS WHICH STATE A CUSTOMER IS IN, and that is
 * recorded here rather than discovered later. `customers` has no state column,
 * and `locations.address` is a single free-text string that must not be parsed
 * for one — a guessed state silently becomes policy, which is the failure
 * CLAUDE.md names for the unset add-on price. So `ConsentService` refuses
 * *marketing* when it cannot determine the state, rather than applying the
 * federal baseline and calling it compliant: this table's own reason for
 * existing is that several states are stricter than federal.
 *
 * The rules below are therefore applied through `StateMessagingRules::for()`
 * whenever a state is known, and the "unknown" branch is the one production
 * takes today. Closing that needs a structured address, which is a CRM slice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_messaging_rules', function (Blueprint $table): void {
            $table->id();

            // USPS two-letter code, uppercase.
            $table->string('state', 2);

            /*
             * The prohibited window, in the recipient's local time.
             *
             * ⚠️ STORED AS THE PROHIBITED WINDOW, NOT THE PERMITTED ONE, and the
             * difference is not cosmetic. Every one of these statutes is written
             * as "no telephone solicitation before X or after Y", so storing the
             * permitted window would mean inverting each statute by hand on the
             * way in — one inversion done wrong produces a rule that permits
             * exactly the hours it was meant to forbid, and reads correct.
             *
             * The window wraps midnight (21:00 → 08:00), which the comparison in
             * `StateMessagingRule::prohibitsAt()` handles explicitly.
             */
            $table->time('quiet_hours_start');
            $table->time('quiet_hours_end');

            /*
             * Whether marketing needs prior express *written* consent rather
             * than plain express consent (`ConsentType`).
             *
             * Florida's FTSA and Oklahoma's copy of it both require it. This is
             * the column that makes the table do something a quiet-hours check
             * alone would not: it turns an existing, valid consent record into
             * an insufficient one, per state.
             */
            $table->boolean('requires_written_consent')->default(false);

            /*
             * The statute this row encodes.
             *
             * ⚠️ REQUIRED, AND THE REASON IS 314–316's. A rules table whose rows
             * cannot be traced to a statute is a table of somebody's
             * recollection, and the next person to update it has no way to check
             * whether the current value was ever right. A citation is what makes
             * a row falsifiable by a lawyer rather than only by a test.
             */
            $table->string('citation');

            /*
             * ⚠️ REQUIRED, AND IT IS WHAT MAKES THIS TABLE A HISTORY RATHER THAN
             * A SETTING. An amended statute is a **new row**, not an edit: one
             * row per state would mean an operator overwriting Florida's window
             * and leaving no record of what it was before, which matters because
             * `audit_log` cannot hold the change — it is tenant-owned with RLS
             * on `business_id`, and a statute belongs to no tenant. Same
             * reasoning as `compliance_suppressions`' soft removal, and the same
             * precedent: `LegalDocument`'s "this row is the audit record of the
             * act".
             *
             * `StateMessagingRules::for()` therefore picks the newest row whose
             * date has arrived, so a future-dated amendment can be entered the
             * day it is signed and takes effect on its own date without anybody
             * remembering to go back.
             */
            $table->date('effective_from');

            $table->text('notes')->nullable();

            $table->timestamps();

            // One version of one statute per date. A re-entry of the same
            // amendment is a correction of that row, not a third version.
            $table->unique(['state', 'effective_from']);

            // The lookup: newest effective row for a state.
            $table->index(['state', 'effective_from']);
        });

        // The window is a window. A row where the two ends are equal encodes
        // either "always prohibited" or "never prohibited" depending on which
        // way the comparison is written, and both readings are defensible — so
        // the value is refused rather than interpreted.
        DB::statement(<<<'SQL'
            ALTER TABLE state_messaging_rules
                ADD CONSTRAINT state_messaging_rules_window_is_a_window
                CHECK (quiet_hours_start <> quiet_hours_end)
        SQL);

        // Uppercase USPS code. A lowercase 'fl' would be a row no lookup ever
        // matched — the silent shape this whole slice is built to avoid.
        DB::statement(<<<'SQL'
            ALTER TABLE state_messaging_rules
                ADD CONSTRAINT state_messaging_rules_state_is_usps_code
                CHECK (state ~ '^[A-Z]{2}$')
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('state_messaging_rules');
    }
};

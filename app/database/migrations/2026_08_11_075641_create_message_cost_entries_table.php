<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The **internal** cost ledger — true carrier cost per send (T137 R9,
 * decision 2134).
 *
 * ⚠️ **TWO BOOKS, AND CONFLATING THEM IS THE WHOLE RISK R9 IS GUARDING AGAINST.**
 *
 *   retail    `credit_ledger` — **1 credit per send.** An SMS, an MMS, or the
 *             SMS+MMS pair sent together on review reactivation are each exactly
 *             one credit. This is what the tenant is charged.
 *   internal  this table — cents, for margin visibility: segments, MMS fees,
 *             billable inbound from the AI two-way conversation, and fees on
 *             messages that were never delivered.
 *
 * ⛔ **NEVER SHOWN AS RETAIL AND NEVER HARDCODED** — R9's own words. The "never
 * shown" half is mechanical: an `ArchitectureTest` lint makes
 * `App\Services\Billing\MessageCostLedger` the only file in `app/` that may
 * touch the model, so no resource, screen or export can reach it. The "never
 * hardcoded" half is that a rate belongs in the rate schedule; **this table
 * stores what a send actually cost, never what a send is worth.**
 *
 * ⚠️ **TENANT-OWNED, WITH RLS, EVEN THOUGH NO TENANT MAY EVER SEE IT.** The
 * temptation is to make it platform-scoped like `stripe_events`, since it is our
 * margin rather than their data. That would be wrong twice: the rows are
 * per-tenant by construction and a per-tenant total is exactly what a support
 * question asks for, and — the load-bearing half — dropping the tenant column
 * would make "what did this tenant cost us" a query nothing constrains. `29`
 * §2's isolation rule is about the boundary existing, not about who is allowed
 * through it.
 *
 * ⚠️ **APPEND-ONLY, AND FOR A DIFFERENT REASON FROM `credit_ledger`.** There the
 * running balance makes an edit unrepairable. Here it is that a cost row is
 * evidence of a charge a carrier has already made: an undelivered-message fee
 * arrives *after* the send it belongs to, and rewriting the send's row to
 * absorb it would make the ledger a mutable estimate rather than a record. The
 * fee is its own row — see `App\Enums\MessageCostKind::UndeliveredFee`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_cost_entries', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // A string cast to App\Enums\MessageCostKind — never a Postgres enum
            // type (`CLAUDE.md`, decision 863).
            $table->string('kind');

            /*
             * ⚠️ THE COLUMN IS `cost_cents` AND THE NAME IS LOAD-BEARING.
             * `BillingTest`'s money lint keys on this codebase's `*_cents`
             * convention to decide which columns are money — a column called
             * `cost` would be invisible to it, and the lint says so by refusing
             * that name outright.
             *
             * Signed, deliberately. A carrier credit — a refunded fee on a
             * message that was billed and then reversed — is genuinely negative,
             * and forbidding it here would push whoever meets one into editing
             * the original row, which is the mutation this table exists to
             * prevent.
             */
            $table->integer('cost_cents');

            // ISO 4217, beside the minor units, because `Money`'s own docblock
            // says the currency is part of the value and not context around it.
            // Multi-currency is in scope (2058) and a carrier bills in its own
            // currency regardless of what the tenant pays in.
            $table->char('currency', 3);

            /*
             * How many carrier segments this send was billed as.
             *
             * ⚠️ NULLABLE, BECAUSE THREE OF THE FIVE KINDS HAVE NO SEGMENTS. An
             * undelivered-message fee is a fee, not a message; MMS is billed per
             * message plus media rather than per segment. Zero would be a
             * *claim* that there were none, which is a different and false
             * statement — 290's mistake.
             */
            $table->unsignedSmallInteger('segments')->nullable();

            /*
             * What this cost belongs to — the outreach row, the inbound message,
             * the delivery receipt.
             *
             * Polymorphic by hand rather than by `morphs()`, matching
             * `credit_ledger`: the referenced tables live across three domains
             * that are being built by three different lanes right now, and a
             * foreign key would make this migration wait for all of them.
             * Both-or-neither is enforced by a CHECK below rather than by
             * convention.
             */
            $table->string('ref_type')->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();

            /*
             * ⚠️ THE IDEMPOTENCY KEY FOR A COST ROW, AND IT IS §3 RAIL 1's
             * "a send can never be double-charged" on the internal book.
             *
             * A delivery receipt can arrive twice and a job can be retried; both
             * would otherwise write the carrier's cost twice and make margin
             * look worse than it is, permanently, in a table nobody reconciles.
             * UNIQUE is the mechanism — an INSERT that conflicts is the claim
             * already having been made, never a check-then-insert (350).
             */
            $table->string('idempotency_key')->unique();

            $table->timestamp('created_at');

            // "What did this tenant cost us, over this period" is the only
            // question this table is asked.
            $table->index(['business_id', 'created_at']);
        });

        DB::statement('ALTER TABLE message_cost_entries ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE message_cost_entries FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON message_cost_entries
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);

        // A reference is both halves or neither. Half of one is a pointer at
        // nothing, and it fails at read time rather than at write time —
        // `credit_ledger`'s rule, restated because the same writer shape
        // produces the same defect.
        DB::statement(<<<'SQL'
            ALTER TABLE message_cost_entries
                ADD CONSTRAINT message_cost_entries_reference_is_whole
                CHECK ((ref_type IS NULL) = (ref_id IS NULL))
        SQL);

        /*
         * ⚠️ A COST OF ZERO IS REFUSED, AND IT IS THE SAME REFUSAL
         * `CreditLedger` MAKES ABOUT A ZERO MOVEMENT. A zero-cent row is what a
         * writer with a silently-empty argument leaves behind — an unconfigured
         * rate schedule returning nothing, most likely — and afterwards it
         * cannot be told apart from a send that genuinely cost nothing. On the
         * ledger that measures margin, "we do not know" reading as "free" is the
         * error that makes the whole table say the wrong thing.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE message_cost_entries
                ADD CONSTRAINT message_cost_entries_cost_is_not_zero
                CHECK (cost_cents <> 0)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('message_cost_entries');
    }
};

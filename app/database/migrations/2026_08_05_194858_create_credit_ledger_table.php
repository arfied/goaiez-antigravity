<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The credit balance and every movement of it (`DATA-MODEL` §Credits & broadcasts,
 * `29` §11.2 row 20).
 *
 * **APPEND-ONLY** (`29` §299 lists it beside `audit_log`), and **the head row is
 * the authoritative balance** — `balance_after` on the highest id for a business.
 * That is a ruling, not an implementation detail: `subscriptions.sms_credits_used`
 * exists and is **never** written as a mirror of this table. Two stores holding one
 * number is decision 286's lossy boolean in a different costume, and the one that
 * loses is whichever a reader happens to ask.
 *
 * ⚠️ **THIS DOCBLOCK SAID "IS ALREADY REPORTED OVER `/api/me`" AND THAT STOPPED
 * BEING TRUE ON 2026-08-13** (3111, 3240). It was reported, permanently as null and
 * 0, by a resource reading columns nothing writes; both fields were removed from
 * the endpoint. The ruling above is untouched and is if anything easier to keep now
 * — there is no longer a plan-pool field on the wire for this table's head to drift
 * into.
 *
 * ⚠️ **SCOPE IS THE PURCHASED POOL ONLY.** Row 20 marks the plan-allocation pool
 * ⛔ blocked on open question F and states the split itself: *"the plan-allocation
 * pool's size is open question F; the purchased pool ($50/1,000) is settled and
 * testable now"*. Nothing here mints a plan allowance — see `CreditKind::Grant`.
 *
 * ⚠️ **NO WRITER OUTSIDE `CreditLedger`** — still true, and still enforced by a
 * `BillingTest` lint rather than remembered. What ships here is the store, the
 * chokepoint and **a reader with a real caller** — 620's inversion is the failure
 * this table is designed against, because a ledger is exactly the kind of thing
 * written constantly and read rarely, and a missing reader shows up as nothing at
 * all rather than as an empty screen.
 *
 * ⚠️ **THIS DOCBLOCK SAID "AND NO PRODUCTION WRITER AT ALL YET" UNTIL 2026-08-13,
 * AND HAD BEEN FALSE FOR DAYS** (decision 3108). Three production writers reach
 * this table now: `RunCampaignJob` → `PlatformMessageSender` and the review-invite
 * path, both debiting `CreditKind::Consume`, and `Support\Accounts` →
 * `CreditGrants`, writing `Adjust`. The sentence claimed the absence was
 * *"deliberate rather than an oversight of decision 272's shape"*, which is the
 * shape `CLAUDE.md` warns about at 314–316: **a docblock asserting a state of
 * affairs is what stops the next reviewer checking whether it still holds.**
 *
 * ⛔ **WHAT IS STILL ABSENT IS THE FUNDER, AND IT IS THE LOAD-BEARING HALF**
 * (3102). Nothing in `app/` constructs `CreditKind::Purchase`, and
 * `CreditLedger::record()` refuses a movement below zero — so a real tenant starts
 * at 0 and **every credited send refuses** until a support operator posts an
 * `Adjust`. That is fail-closed on purpose while 2059's cap-versus-credits
 * question is open, and a lint in `BillingTest` goes red the day somebody builds
 * the purchase flow, so it cannot be armed silently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_ledger', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // The movement, in whole credits. Signed: negative spends.
            //
            // NOT money and deliberately not `App\Support\Money` — a credit is a
            // unit of send, priced at $50/1,000 when bought, and conflating the
            // count with the cents paid for it is how a pack bought at one price
            // gets valued at another after a price change. The money lives on the
            // purchase row that `ref_type`/`ref_id` points at.
            $table->integer('delta');

            // The balance this row left behind, so a reader needs one row rather
            // than a SUM over the tenant's whole history. It is the authoritative
            // figure; a test asserts it always equals the running sum, which is
            // what catches a writer that computed it wrong.
            $table->integer('balance_after');

            // A string cast to App\Enums\CreditKind — never a Postgres enum type
            // (decision 863). `DATA-MODEL` says `credit_kind`; `CLAUDE.md` forbids
            // it and an ConventionsTest lint fails the build on `->enum(`.
            $table->string('kind');

            // What the movement points at — a purchase, a broadcast, a refund.
            // Nullable because an operator adjustment has no referent, which is
            // exactly why that kind is the one required to carry a reason.
            $table->string('ref_type')->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();

            // Why the balance moved, in words a person can read a year later.
            $table->string('reason')->nullable();

            // Who moved it. A user identifier or a system actor string, matching
            // the `$actor` argument every service in this codebase already takes —
            // and not a foreign key, for `customer_imports.attested_by`'s reason:
            // the record has to survive the user row being deleted.
            $table->string('created_by');

            // No updated_at. An append-only row has no meaningful one, and the
            // model declares `UPDATED_AT = null` to match.
            $table->timestamp('created_at')->nullable();

            // `DATA-MODEL` §Key indexes names exactly this one. RLS predicates
            // business_id on every query and Postgres does not index a foreign key
            // automatically — the MySQL habit that does not transfer, and the
            // omission decisions 314–316 caught on `destination_clicks`.
            //
            // `id DESC` because the reader's whole job is the head row.
            $table->index(['business_id', 'id']);
        });

        // A row that moved nothing records nothing, and it is what a writer with a
        // silently-zero argument would leave behind — indistinguishable afterwards
        // from a deliberate no-op.
        DB::statement(<<<'SQL'
            ALTER TABLE credit_ledger
                ADD CONSTRAINT credit_ledger_delta_is_never_zero
                CHECK (delta <> 0)
        SQL);

        // The balance cannot go negative. A tenant who has run out has run out;
        // a negative balance is an overdraft nobody agreed to extend, and it would
        // read as one on the screen that eventually shows it.
        DB::statement(<<<'SQL'
            ALTER TABLE credit_ledger
                ADD CONSTRAINT credit_ledger_balance_is_never_negative
                CHECK (balance_after >= 0)
        SQL);

        // The vocabulary, at the database, because the enum stops a bad value
        // reaching the model and this stops the repair script that reached neither
        // — decision 216's reasoning, and 303–316's three layers.
        DB::statement(<<<'SQL'
            ALTER TABLE credit_ledger
                ADD CONSTRAINT credit_ledger_kind_is_known
                CHECK (kind IN ('purchase', 'consume', 'grant', 'refund', 'adjust'))
        SQL);

        // Sign per kind, for the two kinds whose direction is not a judgement
        // call. `refund` and `adjust` are deliberately absent: a refund runs both
        // ways depending on whether credits or money went back, and constraining
        // it here would enforce a decision nobody has made.
        DB::statement(<<<'SQL'
            ALTER TABLE credit_ledger
                ADD CONSTRAINT credit_ledger_sign_matches_kind
                CHECK (
                    (kind IN ('purchase', 'grant') AND delta > 0)
                    OR (kind = 'consume' AND delta < 0)
                    OR kind IN ('refund', 'adjust')
                )
        SQL);

        // An adjustment with no reason is an unexplained balance change, which is
        // the one thing support will be asked about and the one thing the row
        // would not answer. Empty and whitespace both refused, on
        // `customer_imports_statement_version_is_present`'s precedent.
        DB::statement(<<<'SQL'
            ALTER TABLE credit_ledger
                ADD CONSTRAINT credit_ledger_adjustment_is_explained
                CHECK (kind <> 'adjust' OR (reason IS NOT NULL AND btrim(reason) <> ''))
        SQL);

        // A reference is both halves or neither. One without the other is a
        // pointer at nothing, and it fails silently at read time rather than here.
        DB::statement(<<<'SQL'
            ALTER TABLE credit_ledger
                ADD CONSTRAINT credit_ledger_reference_is_whole
                CHECK ((ref_type IS NULL) = (ref_id IS NULL))
        SQL);

        DB::statement('ALTER TABLE credit_ledger ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE credit_ledger FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON credit_ledger
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_ledger');
    }
};

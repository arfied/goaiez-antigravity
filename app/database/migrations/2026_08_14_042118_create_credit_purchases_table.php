<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One attempt to buy credit — the funder decision 3102 recorded as missing.
 *
 * ⛔ **UNTIL THIS TABLE, `CreditKind::Purchase` WAS CONSTRUCTED NOWHERE IN `app/`
 * AND A LINT FAILED THE BUILD ON IT** (3119, narrowed at 3338). Every tenant's
 * top-up pool was permanently zero on all three products, and 3447 named the
 * consequence: three separate owner rulings — 3438's metering exemption, 3441's
 * active-plan gate and 3443's grandfathering — *"all describe money moving"* and
 * none of them could be felt.
 *
 * ## Why a row exists before the money moves
 *
 * ⚠️ **2056 MAKES THE WEBHOOK THE SOURCE OF TRUTH ON BOTH GATEWAYS, WHICH MEANS
 * THE CHARGE AND THE CREDIT ARE TWO EVENTS AND SOMETHING HAS TO SIT BETWEEN
 * THEM.** This row is that thing. It is written *before* the gateway is called,
 * carrying the SKU, the price, the units and the confirmation, so that when a
 * notification arrives the only question left is "does what they paid match what
 * this row was opened for" — never "what should this transaction buy", which is a
 * question a notification must not be trusted to answer.
 *
 * ⛔ **CREDITING WHATEVER THE NOTIFICATION ASKS FOR IS A FREE-CREDIT HOLE**, and
 * it is the failure this shape exists to make impossible: `price_cents` and
 * `units` are fixed at intent from the registry, and settlement compares the
 * amount actually paid against `price_cents` before the ledger is touched.
 *
 * ## Idempotency, in two layers
 *
 * ⚠️ **BOTH GATEWAYS REDELIVER AND BOTH CAN DELIVER OUT OF ORDER**, so "credit
 * exactly once per transaction" is the property this whole slice lives on.
 *
 *   1. `status` is claimed by a conditional `UPDATE … WHERE status IN
 *      ('pending','authorized')`, which is atomic in Postgres and is a claim
 *      rather than a check-then-insert (350's lesson, `insertOrIgnore`'s shape
 *      expressed on an existing row).
 *   2. **A partial unique index on `credit_ledger`** — added in the migration
 *      beside this one — makes a second `purchase` row pointing at the same
 *      purchase impossible at the database, whatever this column says.
 *
 * Neither layer is redundant: the first makes a replay a silent no-op, which is
 * what a webhook needs; the second is what holds if the first is ever edited
 * wrong, and it fails loudly rather than doubling somebody's balance.
 *
 * ⚠️ **`gateway_transaction_id` IS UNIQUE AND NULLABLE.** Postgres permits many
 * NULLs in a unique index, so a pending Stripe purchase (no transaction until the
 * webhook) and an Authorize.Net one (a transaction id from the synchronous
 * response) coexist under one constraint. What it forbids is two purchases
 * claiming the same vendor transaction, which is the shape a hand-written
 * reconciliation would produce.
 *
 * ## The confirmation is a record, not a checkbox
 *
 * `CLAUDE.md` reserves CONFIRM for three things and *"anything that spends
 * money"* is one; a top-up is the plainest case in the product. `confirmed_actor`,
 * `confirmed_at`, `confirmation_wording` and `confirmed_amount_cents` are all NOT
 * NULL, on the same reasoning as every consent record here: the question a
 * chargeback asks is *what did you show them*, and a boolean cannot answer it.
 * The amount is stored because a price that moved between the screen and the
 * charge would otherwise be a figure the customer never agreed to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_purchases', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // Strings cast to App\Enums\* — never a Postgres enum type
            // (`CLAUDE.md`, decision 863). Each has a CHECK below for 216's
            // second layer.
            $table->string('product');
            $table->string('tier');
            $table->string('gateway');
            $table->string('status')->default('pending');

            // What the card is charged: integer cents plus the currency they are
            // in, `18` §Money handling. Read from `credits.topup.*` at intent and
            // never recomputed, so a price change prices future purchases and
            // leaves this one alone — the same guarantee `ai_calls` gets from
            // storing the charge rather than deriving it (3358).
            $table->integer('price_cents');
            $table->string('currency', 3);

            // ⛔ WHAT LANDS IN THE POOL, IN THE PRODUCT'S OWN LEDGER UNITS —
            // whole sends for SMS and email, HUNDREDTHS OF A CENT for AI. The
            // conversion happened once, in TopUpCatalog, through
            // CreditProduct::ledgerUnitsFromGrant(). ⚠️ This column and
            // `price_cents` are denominated differently on the AI product and
            // that is 3331's hazard sitting in two adjacent columns: 20000 cents
            // paid, 3000000 units granted.
            $table->integer('units');

            // The seed exactly as the registry stated it, kept so a mismatch can
            // be reported in the denomination somebody typed. Never spent.
            $table->integer('grant_seed');

            // Our own handle, sent to the gateway and echoed back: Stripe carries
            // it in session metadata, Authorize.Net in `refId` (20 characters,
            // returned as `merchantReferenceId`). Unique across every tenant,
            // because it is what a notification is resolved by and a notification
            // arrives with no tenant established.
            $table->string('reference')->unique();

            // The vendor's own transaction identifier. Unique, nullable — see the
            // docblock: many NULLs are permitted and two rows claiming one
            // transaction are not.
            $table->string('gateway_transaction_id')->nullable()->unique();

            // The CONFIRM record. All four NOT NULL: a purchase with no
            // confirmation is not a purchase this application makes.
            //
            // ⚠️ `confirmed_actor` AND NOT `confirmed_by`, WHICH IS THE OBVIOUS
            // NAME AND WAS THE FIRST ONE. `tenant_deletion_requests.confirmed_by`
            // exists, and `TenancyTest`'s deletion chokepoint lint matches that
            // bare column name across every file in `app/` — the strictest of the
            // three lifecycle guards, because a deletion past its window is undone
            // by nobody. A credit purchase reusing the name reddened it
            // immediately. **The lint is right and this column moved**: narrowing
            // a guard on account destruction so that a billing table can have a
            // prettier column is 511's failure with the stakes reversed.
            $table->string('confirmed_actor');
            $table->integer('confirmed_amount_cents');
            $table->string('confirmation_wording');
            $table->timestamp('confirmed_at');

            // When the money moved and when the credit did — two different
            // instants, which is the whole reason this table exists.
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('credited_at')->nullable();

            // Why an unhappy row ended where it did, in words an operator reads.
            // Nullable, and a CHECK requires one on the two failing states.
            $table->string('failure_reason')->nullable();

            $table->timestamps();

            // RLS predicates business_id on every query and Postgres does not
            // index a foreign key automatically — the MySQL habit that does not
            // transfer, and the omission 314–316 caught on `destination_clicks`.
            // The reader's job is "this tenant's purchases, newest first" and
            // "this tenant's unsettled purchases", which is what an operator
            // looking for a paid-but-uncredited row asks.
            $table->index(['business_id', 'status', 'id']);
        });

        // The vocabularies, at the database. The PHP enum stops a bad value
        // reaching the model and this stops the repair script that reached
        // neither (216, 303–316's three layers).
        DB::statement(<<<'SQL'
            ALTER TABLE credit_purchases
                ADD CONSTRAINT credit_purchases_product_is_known
                CHECK (product IN ('sms', 'email', 'ai'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE credit_purchases
                ADD CONSTRAINT credit_purchases_tier_is_known
                CHECK (tier IN ('automatic', 'manual'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE credit_purchases
                ADD CONSTRAINT credit_purchases_gateway_is_known
                CHECK (gateway IN ('stripe', 'authorize_net'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE credit_purchases
                ADD CONSTRAINT credit_purchases_status_is_known
                CHECK (status IN ('pending', 'authorized', 'credited', 'failed', 'mismatched'))
        SQL);

        // A pack that charges nothing or grants nothing is the Ops typo that
        // looks right on every screen: one charges nobody and credits a tenant,
        // the other charges $50 and moves no balance. Refused in TopUpCatalog
        // and refused here, for 216's reason.
        DB::statement(<<<'SQL'
            ALTER TABLE credit_purchases
                ADD CONSTRAINT credit_purchases_buys_something
                CHECK (price_cents > 0 AND units > 0 AND grant_seed > 0)
        SQL);

        // ⚠️ THE CONFIRMATION IS THE AMOUNT THAT WAS SHOWN, SO IT HAS TO BE AN
        // AMOUNT. A zero here would be a confirmation of nothing wearing the
        // record's clothes.
        DB::statement(<<<'SQL'
            ALTER TABLE credit_purchases
                ADD CONSTRAINT credit_purchases_confirmation_is_whole
                CHECK (
                    confirmed_amount_cents > 0
                    AND btrim(confirmed_actor) <> ''
                    AND btrim(confirmation_wording) <> ''
                )
        SQL);

        // ⛔ A CREDITED ROW HAS A CREDITED_AT AND AN UNCREDITED ROW HAS NONE.
        // The column is what an operator reads to answer "did they get it", and
        // a `credited` status with a null timestamp — or the reverse — is a row
        // that answers the question two ways.
        DB::statement(<<<'SQL'
            ALTER TABLE credit_purchases
                ADD CONSTRAINT credit_purchases_credited_is_dated
                CHECK ((status = 'credited') = (credited_at IS NOT NULL))
        SQL);

        // An unhappy ending with no reason is the row support is asked about and
        // the one it cannot answer — `credit_ledger_adjustment_is_explained`'s
        // reasoning, one table over.
        DB::statement(<<<'SQL'
            ALTER TABLE credit_purchases
                ADD CONSTRAINT credit_purchases_failure_is_explained
                CHECK (
                    status NOT IN ('failed', 'mismatched')
                    OR (failure_reason IS NOT NULL AND btrim(failure_reason) <> '')
                )
        SQL);

        // ⚠️ MONEY MOVED MEANS A TRANSACTION ID. `authorized`, `credited` and
        // `mismatched` all mean a card was charged, and a charge this
        // application cannot name is a charge it cannot reconcile or refund.
        DB::statement(<<<'SQL'
            ALTER TABLE credit_purchases
                ADD CONSTRAINT credit_purchases_charged_rows_name_the_transaction
                CHECK (
                    status NOT IN ('authorized', 'credited', 'mismatched')
                    OR gateway_transaction_id IS NOT NULL
                )
        SQL);

        DB::statement('ALTER TABLE credit_purchases ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE credit_purchases FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON credit_purchases
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);

        /*
         * The index a tenantless webhook resolves a purchase through —
         * `stripe_customers`' shape exactly (682), and the fifth model in this
         * schema to sit outside the tenant boundary while naming a tenant.
         *
         * ⛔ **THE TABLE ABOVE CANNOT ANSWER THIS QUESTION AND THAT IS NOT A
         * DESIGN CHOICE.** `credit_purchases` is `ENABLE` + `FORCE ROW LEVEL
         * SECURITY` on `app.business_id`, and a webhook arrives at a public URL
         * with no session, no cookie and no user. A handler with no tenant reads
         * **zero rows rather than an error** — and `withoutGlobalScopes()` does
         * not help, because RLS sits beneath the application scope (569). The
         * four earlier readers that met this wall all refused to widen the
         * boundary and served the question from a store that has no tenant. This
         * is the fifth, answered the same way, and **no policy on any
         * tenant-owned table changed.**
         *
         * ⚠️ **THE AUTHORITATIVE COPY IS `credit_purchases`.** This is one fact
         * and one index of it, written in the same transaction by the same
         * service, in the relationship a unique index has to its column. If the
         * two ever disagree the tenant-owned row wins — stated because the
         * opposite guess is the natural one for whoever finds the mismatch, and
         * acting on it would let a stale pointer credit the wrong tenant.
         *
         * ⚠️ **WHAT IS ON IT: TWO OPAQUE HANDLES, A VENDOR NAME AND A BUSINESS
         * ID.** No amount, no product, no card metadata, no confirmation. There
         * is nothing here a tenant could be harmed by another tenant seeing, and
         * the handler's very next line after reading it is `Tenancy::actingAs()`,
         * so everything downstream is ordinarily scoped.
         *
         * ⚠️ **TWO LOOKUP COLUMNS BECAUSE THE TWO GATEWAYS CORRELATE
         * DIFFERENTLY, AND ONE OF THEM IS DOCUMENTED AS UNRELIABLE.** Stripe
         * carries our `reference` in session metadata on every event. Authorize.
         * Net echoes `refId` back as `payload.merchantReferenceId` — and that
         * field has a public history of disappearing from notifications for days
         * at a time. So the transaction id, which we learn from the synchronous
         * charge response, is the primary handle on that gateway and the
         * reference is the fallback.
         */
        Schema::create('credit_purchase_references', function (Blueprint $table): void {
            // Our own handle as the primary key. There is no second identity to
            // mint: the row exists only to answer "whose purchase is this", and a
            // surrogate id would be a column nothing ever selects by —
            // `stripe_customers.stripe_customer_id`'s reasoning.
            $table->string('reference')->primary();

            // ⚠️ RESTRICTED, NOT CASCADING, for `stripe_customers`' reason: a
            // business row disappearing while a charge is in flight means an
            // inbound payment notification that resolves to nothing, silently,
            // on the vendor that takes money.
            $table->foreignId('business_id')->constrained()->restrictOnDelete();

            // ⚠️ NOT UNIQUE, UNLIKE `stripe_customers.business_id`. A business has
            // one Stripe customer for ever and many top-up purchases, which is the
            // one structural difference between this index and its precedent.
            $table->string('gateway');

            $table->string('gateway_transaction_id')->nullable()->unique();

            $table->timestamp('created_at');
        });

        DB::statement('ALTER TABLE credit_purchase_references ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE credit_purchase_references FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY platform_scoped ON credit_purchase_references
                FOR ALL USING (true) WITH CHECK (true)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE credit_purchase_references
                ADD CONSTRAINT credit_purchase_references_gateway_is_known
                CHECK (gateway IN ('stripe', 'authorize_net'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_purchase_references');
        Schema::dropIfExists('credit_purchases');
    }
};

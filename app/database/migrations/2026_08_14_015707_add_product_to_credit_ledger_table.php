<?php

declare(strict_types=1);

use App\Services\Billing\CreditLedger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Three products, on one ledger (decision 3419).
 *
 * 3298 grants three separate allotments a month — 500 SMS, 1,000 emails and a
 * month of AI credit — and 3307 splits each into a monthly pool that resets and a
 * top-up pool that does not. This table could express the second half and not the
 * first: `pool` landed at 3357 and `delta` counted whole SMS sends, so an email
 * spend and a text spend were the same row and an AI balance could not exist at
 * all. **`product` is the missing dimension. Six balances, one append-only
 * table.**
 *
 * ⚠️ **ONE LEDGER RATHER THAN A SECOND BOOK FOR AI, DECIDED AND NOT DRIFTED
 * INTO.** A separate money ledger was the obvious answer to the unit problem
 * below and was rejected: one balance per tenant beats two books to reconcile,
 * and this table already carries the append-only model events, the per-movement
 * lock on the business row, the RLS policy and the chokepoint lint that a second
 * one would have to grow from nothing.
 *
 * ⛔ **`balance_after` CHANGES MEANING FOR THE SECOND TIME AND THIS IS THE WHOLE
 * MIGRATION.** It was the tenant's balance; 3357 made it *that pool's* balance;
 * it is now *that product and pool's* balance. {@see CreditLedger::balance()}
 * requires a product for that reason, and there is deliberately no method
 * anywhere that adds the six together — 500 sends plus 300,000 hundredths of a
 * cent is a number with no meaning, and the type signature is what makes it
 * unwritable.
 *
 * ⛔ **THE UNIT VARIES BY PRODUCT, AND READING ONE INTO THE OTHER IS A NAMED,
 * PREDICTED DEFECT** (3331). SMS and email are whole sends. **AI is money in
 * hundredths of a cent**, because `ai_calls.retail_hundredths_cents` is what
 * debits it and a single call charges a fraction of a cent. The registry states
 * the AI grant in *cents* (`credits.monthly_grant.ai_cents` — the figure is the
 * seed's to state and is deliberately not copied here, because the owner moved it
 * at 9180 and a migration cannot be re-run to correct itself), so granting it
 * straight would under-grant by a hundred **with the
 * balance looking entirely plausible the whole way** — no exception, no
 * obviously-wrong figure. The conversion is `App\Enums\CreditUnit::fromCents()`,
 * reached only through `App\Enums\CreditProduct::ledgerUnitsFromGrant()`, and
 * `CreditProductsTest` drives a 100× error red three ways.
 *
 * ⚠️ **`balance_after` AND `delta` STAY `int4`, WITH THE HEADROOM CHECKED RATHER
 * THAN ASSUMED.** The AI pool is the only one whose figures are large: $50 of
 * retail credit is 500,000 units and the largest top-up SKU (3303's shape at
 * 9181's rungs, $300 granting $400) would be 4,000,000. `int4` tops out at
 * 2,147,483,647 — **about $214,748 of unspent AI credit, which is a fact about
 * the UNIT and does not move when the owner moves a price** — or five hundred
 * untouched manual top-ups, which does. **And Postgres
 * raises `integer out of range` rather than wrapping**, so the failure is loud
 * and at the write rather than silent and in the balance. A wider column was not
 * worth rewriting an append-only table for.
 *
 * ⚠️ **EVERY EXISTING ROW IS AN SMS ROW, AND THE BACKFILL RESTATES A FACT RATHER
 * THAN CHOOSING ONE.** 3357 records that `credit_ledger` *"counts SMS credits and
 * nothing else — one credit per send (T137 R9, decision 2144)"*, `CreditPool`'s
 * own docblock said the same in as many words, and the only two debiting callers
 * were `SendCredits` (SMS) and `EmailCredits` — whose debit landed on the
 * undifferentiated balance and which its own docblock names as *"the one place
 * that changes when the ledger learns which product a unit belongs to."*
 * Historical email debits are therefore backfilled as SMS along with everything
 * else; they were spending the SMS balance in fact as well as in the column, and
 * re-labelling them now would move a balance that was already spent.
 *
 * ⛔ **A DEFAULT ON THE `ALTER`, NEVER AN `UPDATE`, AND 3345 IS THE REASON.** This
 * table is `ENABLE` + `FORCE ROW LEVEL SECURITY` on `app.business_id`, and a
 * migration establishes no tenant — so `UPDATE credit_ledger SET product = 'sms'`
 * matches **zero rows and reports success**, leaving a column somebody then relies
 * on filled with nothing. DDL is not subject to row-level security, so the default
 * fills every existing row as part of the `ALTER` itself.
 *
 * ⚠️ **THE COLUMN KEEPS ITS DEFAULT FOR 3357's REASON, NOT OUT OF HABIT.**
 * `credit_ledger` has exactly one writer and a `BillingTest` lint enforces that,
 * so there is no second writer for a `NOT NULL` to catch; and the third-layer
 * probes in `CreditLedgerTest` insert raw rows naming only the columns each
 * constraint is about (386's "one violation per statement"), which a defaultless
 * column would turn into not-null violations, retiring the layer they pin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_ledger', function (Blueprint $table): void {
            $table->string('product')->default('sms');
        });

        // The vocabulary at the database, for `credit_ledger_kind_is_known`'s and
        // `credit_ledger_pool_is_known`'s reason: the PHP enum stops a bad value
        // reaching the model and this stops the repair script that reached neither
        // (216, 303–316).
        DB::statement(<<<'SQL'
            ALTER TABLE credit_ledger
                ADD CONSTRAINT credit_ledger_product_is_known
                CHECK (product IN ('sms', 'email', 'ai'))
        SQL);

        // ⚠️ THE READER'S JOB IS NOW THE HEAD ROW PER (product, pool), SO THE
        // `(business_id, pool, id)` INDEX 3357 ADDED NO LONGER COVERS IT. Postgres
        // does not index a foreign key automatically — the MySQL habit that does
        // not transfer, and the omission decisions 314–316 caught on
        // `destination_clicks`.
        //
        // The narrower index is dropped rather than kept: `(business_id, pool, id)`
        // is not a prefix of this one, but nothing reads a pool across products any
        // more — `CreditLedger::poolBalance()` is the only query that touched it and
        // it now predicates on both columns. Two overlapping indexes on a table
        // written once per message is write cost for a reader that no longer exists.
        Schema::table('credit_ledger', function (Blueprint $table): void {
            $table->index(['business_id', 'product', 'pool', 'id']);
            $table->dropIndex(['business_id', 'pool', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('credit_ledger', function (Blueprint $table): void {
            $table->index(['business_id', 'pool', 'id']);
            $table->dropIndex(['business_id', 'product', 'pool', 'id']);
        });

        DB::statement('ALTER TABLE credit_ledger DROP CONSTRAINT credit_ledger_product_is_known');

        Schema::table('credit_ledger', function (Blueprint $table): void {
            $table->dropColumn('product');
        });
    }
};

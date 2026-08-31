<?php

declare(strict_types=1);

use App\Services\Billing\CreditLedger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The two pools, on one balance (decision 3307).
 *
 * The owner's ruling: *"top up don't expire they stay in the account on month
 * CREdits reset each month"*, and *"keep the credit pool sperate … monthly runs
 * out it takes from the top up pools."* This adds the discriminator that makes
 * those two lifetimes expressible in a table that has only ever held one.
 *
 * ⚠️ **`balance_after` CHANGES MEANING, AND THAT IS THE WHOLE MIGRATION.** It was
 * the tenant's balance after this row; it is now *this pool's* balance after this
 * row. The tenant's balance is the two heads added together
 * ({@see CreditLedger::balance()}). Nothing else could work:
 * a single running total cannot say how much of itself is about to expire, and
 * 3315 asks for the two to be **reported separately** anyway — *"not one number
 * but two balances … keep the credit pool sperate."*
 *
 * ⚠️ **EVERY EXISTING ROW IS A TOP-UP ROW, AND THE BACKFILL IS NOT A GUESS.**
 * `credit_ledger`'s own docblock says its scope was *"the purchased pool only"*
 * and `App\Enums\CreditKind::Grant` was constructed nowhere in `app/` (3103, with
 * a lint failing the build on it), so no row in this table can be a monthly
 * grant. The backfill therefore restates a fact rather than choosing one — and
 * because the old `balance_after` counted a history that was entirely top-up, the
 * per-pool running total it leaves behind is arithmetically identical to the one
 * it had.
 *
 * ⚠️ **THE COLUMN KEEPS ITS DEFAULT, DELIBERATELY, AGAINST THE HABIT.** The tidier
 * choice is to drop it afterwards so that a writer which forgets the column fails
 * loudly. Two things argue the other way and they win here: `credit_ledger` has
 * exactly one writer and a `BillingTest` lint enforces that, so there is no second
 * writer to catch; and the third-layer probes in `CreditLedgerTest` insert raw
 * rows naming only the columns each constraint is about, which is decision 386's
 * "one violation per statement" discipline — a `NOT NULL` with no default would
 * turn six check-violation assertions into not-null violations and the layer they
 * pin would stop being tested. **What protects the column is not the absence of a
 * default but the three CHECKs below**, which refuse every combination where the
 * wrong pool would cost somebody credits.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ⚠️ ADDED WITH A DEFAULT RATHER THAN ADDED-THEN-BACKFILLED.
        // An `UPDATE credit_ledger SET pool = 'topup'` would be data manipulation
        // against a table that is ENABLE + FORCE ROW LEVEL SECURITY on
        // `app.business_id` — and a migration establishes no tenant, so
        // `current_setting('app.business_id', true)` is empty, the policy matches
        // no row, and the statement reports success having touched nothing. A
        // silent zero-row backfill behind a column somebody then relies on is the
        // kind of failure that first appears in production. DDL is not subject to
        // row-level security, so the default fills every existing row as part of
        // the ALTER itself.
        Schema::table('credit_ledger', function (Blueprint $table): void {
            $table->string('pool')->default('topup');
        });

        // The vocabulary, at the database, for `credit_ledger_kind_is_known`'s
        // reason: the PHP enum stops a bad value reaching the model and this stops
        // the repair script that reached neither (216, 303–316).
        DB::statement(<<<'SQL'
            ALTER TABLE credit_ledger
                ADD CONSTRAINT credit_ledger_pool_is_known
                CHECK (pool IN ('monthly', 'topup'))
        SQL);

        // `expire` joins the kinds. It is how an append-only ledger ends a grant:
        // a movement against it, never a delete (3307).
        DB::statement('ALTER TABLE credit_ledger DROP CONSTRAINT credit_ledger_kind_is_known');

        DB::statement(<<<'SQL'
            ALTER TABLE credit_ledger
                ADD CONSTRAINT credit_ledger_kind_is_known
                CHECK (kind IN ('purchase', 'consume', 'grant', 'refund', 'adjust', 'expire'))
        SQL);

        // Sign per kind, unchanged except that `expire` only ever decreases.
        DB::statement('ALTER TABLE credit_ledger DROP CONSTRAINT credit_ledger_sign_matches_kind');

        DB::statement(<<<'SQL'
            ALTER TABLE credit_ledger
                ADD CONSTRAINT credit_ledger_sign_matches_kind
                CHECK (
                    (kind IN ('purchase', 'grant') AND delta > 0)
                    OR (kind IN ('consume', 'expire') AND delta < 0)
                    OR kind IN ('refund', 'adjust')
                )
        SQL);

        // ⛔ THE THREE THAT MAKE THE POOLS MEAN SOMETHING.
        //
        // A `grant` outside the monthly pool is an allotment that never expires,
        // which is 3307 inverted and is unrecoverable in an append-only table. An
        // `expire` outside the monthly pool is the reset eating credits somebody
        // paid for — *"top up don't expire"*, the clause with money attached. And
        // a `purchase` in the monthly pool is paid credit carrying an expiry date,
        // the same failure arriving through the funder instead of through the
        // reset.
        //
        // `consume`, `adjust` and `refund` are deliberately unconstrained: which
        // pool each draws from is a runtime question about what is available, and
        // answering it here would freeze the draw order into the schema.
        DB::statement(<<<'SQL'
            ALTER TABLE credit_ledger
                ADD CONSTRAINT credit_ledger_grant_is_monthly
                CHECK (kind <> 'grant' OR pool = 'monthly')
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE credit_ledger
                ADD CONSTRAINT credit_ledger_expiry_is_monthly
                CHECK (kind <> 'expire' OR pool = 'monthly')
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE credit_ledger
                ADD CONSTRAINT credit_ledger_purchase_is_top_up
                CHECK (kind <> 'purchase' OR pool = 'topup')
        SQL);

        // The reader's whole job is now the head row *per pool*, so the existing
        // `(business_id, id)` index no longer covers it. Postgres does not index a
        // foreign key automatically — the MySQL habit that does not transfer, and
        // the omission decisions 314–316 caught on `destination_clicks`.
        Schema::table('credit_ledger', function (Blueprint $table): void {
            $table->index(['business_id', 'pool', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('credit_ledger', function (Blueprint $table): void {
            $table->dropIndex(['business_id', 'pool', 'id']);
        });

        foreach ([
            'credit_ledger_purchase_is_top_up',
            'credit_ledger_expiry_is_monthly',
            'credit_ledger_grant_is_monthly',
            'credit_ledger_pool_is_known',
        ] as $constraint) {
            DB::statement("ALTER TABLE credit_ledger DROP CONSTRAINT {$constraint}");
        }

        DB::statement('ALTER TABLE credit_ledger DROP CONSTRAINT credit_ledger_sign_matches_kind');

        DB::statement(<<<'SQL'
            ALTER TABLE credit_ledger
                ADD CONSTRAINT credit_ledger_sign_matches_kind
                CHECK (
                    (kind IN ('purchase', 'grant') AND delta > 0)
                    OR (kind = 'consume' AND delta < 0)
                    OR kind IN ('refund', 'adjust')
                )
        SQL);

        DB::statement('ALTER TABLE credit_ledger DROP CONSTRAINT credit_ledger_kind_is_known');

        DB::statement(<<<'SQL'
            ALTER TABLE credit_ledger
                ADD CONSTRAINT credit_ledger_kind_is_known
                CHECK (kind IN ('purchase', 'consume', 'grant', 'refund', 'adjust'))
        SQL);

        Schema::table('credit_ledger', function (Blueprint $table): void {
            $table->dropColumn('pool');
        });
    }
};

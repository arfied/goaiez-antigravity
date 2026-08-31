<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A purchase may be credited exactly once, and the database is what says so.
 *
 * ⛔ **THIS IS THE LAYER THAT HOLDS WHEN THE CLAIM IS EDITED WRONG.**
 * `CreditPurchases::settle()` claims a purchase with a conditional `UPDATE …
 * WHERE status IN ('pending','authorized')`, which is atomic and is what makes a
 * redelivered notification a silent no-op. That is the right behaviour and it is
 * **one line of application code away from being wrong** — widen the `WHERE`, add
 * a retry that reloads the row first, catch the wrong exception, and a replayed
 * webhook credits a tenant twice with every screen still balancing, because
 * `credit_ledger` is append-only and a second `purchase` row is as valid-looking
 * as the first.
 *
 * A partial unique index cannot be edited by accident from `app/`. **One
 * `purchase` row per (tenant, referenced purchase), enforced by Postgres.**
 *
 * ⚠️ **PARTIAL, ON `kind = 'purchase'`, AND THE PREDICATE IS LOAD-BEARING.** The
 * same `(ref_type, ref_id)` pair legitimately appears more than once for other
 * kinds — a `Refund` reversing a purchase points at exactly the same row, and
 * that is the correct way to record it. An unconditional unique index would
 * forbid the refund rather than the duplicate, which is the reverse of what is
 * wanted.
 *
 * ⚠️ **`business_id` IS IN THE INDEX THOUGH `ref_id` IS ALREADY GLOBALLY
 * UNIQUE.** `credit_purchases.id` is a global sequence, so the tenant column adds
 * nothing to the uniqueness — it is there because every index on this table
 * leads with it (RLS predicates it on every query) and because a cross-tenant
 * unique index is a lock two tenants can contend on for no reason.
 *
 * ⚠️ **AND IT IS A `CREATE UNIQUE INDEX`, NOT A CONSTRAINT.** Postgres has no
 * partial unique *constraint*; `ADD CONSTRAINT … UNIQUE` does not take a `WHERE`.
 * The index is the only spelling that expresses this, which is worth saying
 * because every other rule on this table arrived as `ADD CONSTRAINT` and a reader
 * comparing them would otherwise think this one had been written loosely.
 *
 * ⚠️ **NO BACKFILL AND NOTHING TO BACK-FILL.** `CreditKind::Purchase` was
 * constructed nowhere in `app/` until the migration beside this one (3102, 3426),
 * so no existing row can violate this — and an `UPDATE` on this table from a
 * migration would match zero rows anyway, because `credit_ledger` is `ENABLE` +
 * `FORCE ROW LEVEL SECURITY` and a migration establishes no tenant (3340, 3430).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX credit_ledger_one_credit_per_purchase
                ON credit_ledger (business_id, ref_type, ref_id)
                WHERE kind = 'purchase'
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS credit_ledger_one_credit_per_purchase');
    }
};

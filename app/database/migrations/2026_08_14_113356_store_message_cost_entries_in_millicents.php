<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The internal cost book changes unit: `cost_cents` becomes `cost_millicents`
 * (decision 3729, superseding 2546's rounding rule).
 *
 * ⚠️ **THE COLUMN WAS ALWAYS THE WRONG SIZE FOR THE THING IT MEASURES, AND
 * EMAIL IS WHAT MADE THAT UNARGUABLE.** 2546 put the *schedule* in millicents —
 * thousandths of a cent — precisely because a carrier SMS costs less than a
 * cent, then converted to cents on the way in and floored the result at 1 so
 * that a sub-cent send did not become a zero the CHECK below refuses. On an SMS
 * at 750 millicents that floor overstates by a third, which 2546 argued for
 * openly: margin looks worse than it is, which is the safe direction.
 *
 * ⛔ **AT AMAZON SES's RATES THE SAME FLOOR OVERSTATES BY A HUNDREDFOLD.** One
 * email costs on the order of 10 millicents — a hundredth of a cent — so every
 * email row would have said 1 cent against a retail price of 2 (3299). A book
 * whose only job is answering *"are we making money"* would have reported a
 * ~200:1 product as roughly 2:1. That is not a conservative rounding, it is a
 * wrong answer that renders perfectly, which is exactly what 3105 warns about.
 *
 * ✅ **THE FIX IS THE ARGUMENT 2546 ALREADY MADE, ONE LEVEL UP.** It converts
 * once on the whole *message* rather than per segment, because rounding early
 * and often is what loses the money. Storing the schedule's own unit converts
 * once on the whole *book* instead — three segments at 750 are 2,250 millicents
 * exactly, and a thousand emails at 10 are 10,000, which is 10 cents rather than
 * a thousand roundings of one. Nothing else about the table changes: it stays
 * append-only, signed, tenant-owned, RLS-forced, and refuses zero.
 *
 * ⚠️ **`cost_millicents` IS DELIBERATELY NOT `*_cents`, AND `BillingTest`'s
 * MONEY LINT GAINS THE SUFFIX IN THE SAME SLICE.** The convention exists so a
 * column's unit is unambiguous; a millicent column named `*_cents` would be the
 * ambiguity the convention was written to prevent. What the lint must not lose
 * is its grip — so it now refuses a fractional `*_millicents` column too.
 * ⚠️ **AND IT HAD TO LEARN RAW SQL BEFORE IT COULD SEE THIS FILE** (3889): the
 * lint scanned `Blueprint` calls, and every column below is declared with
 * `DB::statement()`, so the schema's first `*_millicents` column was invisible
 * to the lint extended for it.
 *
 * ## What this migration needs from the server, and from whoever runs it
 *
 * ⚠️ **`ALTER COLUMN … DROP EXPRESSION` IS POSTGRESQL 13 OR LATER.** It is the
 * step that turns the generated column into an ordinary one, it has no fallback
 * that keeps the values, and on 12 this fails at that statement with the new
 * column already added. `CLAUDE.md`'s stack is 16 and the 3430 trap was
 * reproduced on 16.14, so this is a note for a box, not a doubt about ours.
 *
 * ⚠️ **`ADD COLUMN … STORED` REWRITES THE WHOLE TABLE UNDER `ACCESS EXCLUSIVE`.**
 * A generated column is computed for every existing row, so this is not the
 * catalogue-only `ADD COLUMN` that Postgres 11 made cheap: the table is locked
 * against readers and writers for the duration. `message_cost_entries` is
 * append-only, internal, and has no customer-facing reader, so a pause here
 * delays a cost row rather than a message — but it is a real lock and it belongs
 * in a maintenance window once the table is large.
 *
 * ⛔ **AND THE DEPLOY ORDER PUTS THIS AFTER THE CODE THAT NEEDS IT** (3891).
 * `composer deploy` runs the migrations, and the documented order is
 * code → `.env` → `composer deploy` — so between the new code landing and this
 * statement running, an insert naming `cost_millicents` meets a table that still
 * has `cost_cents` and fails with `SQLSTATE 42703`. The window is very likely
 * empty today, because no customer-facing send is live; since 3881 a failed cost
 * insert also no longer takes the message with it. On a live box the honest
 * sequencing is still to migrate before the sending workers pick up new code.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * ⛔ **THE CONVERSION IS DDL AND NEVER AN `UPDATE` — 3430's RULE, WHICH
         * 3345 LEARNED THE HARD WAY, AND THIS TABLE HAS EXACTLY THE PROPERTY IT
         * WARNS ABOUT.** `message_cost_entries` is `ENABLE` + `FORCE ROW LEVEL
         * SECURITY` with a policy on `app.business_id`, and a migration
         * establishes no tenant — so `UPDATE message_cost_entries SET
         * cost_millicents = cost_cents * 1000` would match **zero rows and
         * report success**, leaving every existing row null. On a fresh install
         * the table is empty and it would pass; in production, where the rows
         * are, the `SET NOT NULL` would be the first thing to know.
         *
         * DDL is not subject to row-level security. A generated column computes
         * the value for every row as part of the `ALTER` itself, and
         * `DROP EXPRESSION` then turns it into an ordinary column keeping what
         * it computed. 3430 used a plain column default; that is not available
         * here, because this value is derived per row rather than constant.
         *
         * bigint rather than integer: a cents column that held ±21 million
         * dollars holds ±21 thousand once it counts thousandths, which is a
         * ceiling a platform-wide book could actually reach.
         *
         * Exact by construction — a cent is a thousand millicents, so no row
         * loses anything on the way across. Rows written *before* this migration
         * still carry the old floor's overstatement baked in and cannot be
         * repaired from here; that is a fact about those rows, not about the
         * column.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE message_cost_entries
                ADD COLUMN cost_millicents bigint
                GENERATED ALWAYS AS (cost_cents::bigint * 1000) STORED
        SQL);

        DB::statement('ALTER TABLE message_cost_entries ALTER COLUMN cost_millicents DROP EXPRESSION');
        DB::statement('ALTER TABLE message_cost_entries ALTER COLUMN cost_millicents SET NOT NULL');

        // The CHECK travels with the column rather than being re-argued: "we do
        // not know" must never be storable as "it was free", on the one ledger
        // that measures margin.
        DB::statement('ALTER TABLE message_cost_entries DROP CONSTRAINT message_cost_entries_cost_is_not_zero');

        Schema::table('message_cost_entries', function (Blueprint $table): void {
            $table->dropColumn('cost_cents');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE message_cost_entries
                ADD CONSTRAINT message_cost_entries_cost_is_not_zero
                CHECK (cost_millicents <> 0)
        SQL);
    }

    /**
     * ⚠️ **LOSSY, AND SAYING SO IS THE POINT.** Going back down re-applies
     * 2546's floor — a sub-cent row becomes one cent, keeping its sign — because
     * the cents column cannot hold what this one can. A round trip is therefore
     * not the identity, and anybody who needs the exact figures after a rollback
     * needs the backup rather than this method.
     *
     * ⛔ **AND "LOSSY" IS TOO GENTLE A WORD FOR WHAT A ROUND TRIP DOES: IT
     * INFLATES** (3899). `down()` then `up()` is not a small loss of precision,
     * it is a **hundredfold overstatement** on the rows this book is actually
     * made of. One email at Amazon SES's à la carte rate is 10 millicents;
     * `down()` floors it to 1 cent and `up()` multiplies that back to 1,000
     * millicents. A thousand emails that genuinely cost 10,000 millicents
     * (10 cents) come back reading 1,000,000 — **$10 instead of $0.10** — and
     * every row renders perfectly, because the number is a legal value in a
     * legal column. An SMS at 750 millicents inflates by a third the same way.
     * ⚠️ **The direction matters**: this makes our own costs look *worse*, so it
     * shows up as a margin that collapsed rather than as an error, and the one
     * table kept for reconciliation against a vendor invoice is the one that
     * disagrees with the invoice by a factor of a hundred. **A rollback followed
     * by a re-apply must be treated as data loss and restored from backup**, not
     * as a schema operation that happens to round.
     *
     * ⛔ **AND "LOSSY" UNDERSTATES IT: IT CAN ALSO REFUSE** (3890). The `::integer`
     * cast raises `SQLSTATE 22003` — integer out of range — for any row above
     * ±2,147,483,647,000 millicents, and it raises it from inside the generated
     * expression, so the `ALTER` aborts and the rollback stops with the schema
     * unchanged. That is the safe direction and is why the cast stays: the
     * alternatives are a silent wrap or a saturating clamp, either of which puts
     * a fabricated figure in a book kept for reconciliation. It takes a single
     * row worth more than twenty-one million dollars, which is exactly the
     * ceiling `up()`'s bigint was chosen to clear.
     *
     * ⚠️ **AND IT IS DDL FOR THE SAME REASON `up()` IS** — an `UPDATE` here
     * would silently restore nothing, which on the way *down* is the more
     * dangerous direction, because the column it was restoring is the one the
     * old code reads.
     */
    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE message_cost_entries
                ADD COLUMN cost_cents integer
                GENERATED ALWAYS AS (
                    (sign(cost_millicents) * greatest(1, round(abs(cost_millicents) / 1000.0)))::integer
                ) STORED
        SQL);

        DB::statement('ALTER TABLE message_cost_entries ALTER COLUMN cost_cents DROP EXPRESSION');
        DB::statement('ALTER TABLE message_cost_entries ALTER COLUMN cost_cents SET NOT NULL');
        DB::statement('ALTER TABLE message_cost_entries DROP CONSTRAINT message_cost_entries_cost_is_not_zero');

        Schema::table('message_cost_entries', function (Blueprint $table): void {
            $table->dropColumn('cost_millicents');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE message_cost_entries
                ADD CONSTRAINT message_cost_entries_cost_is_not_zero
                CHECK (cost_cents <> 0)
        SQL);
    }
};

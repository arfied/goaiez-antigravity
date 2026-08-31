<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The price this customer agreed to, kept for as long as they keep the plan
 * (decisions 3443, 3444, 3518–3547).
 *
 * ## ⚠️ "No money columns here: prices live in Stripe" is overturned, and here is
 * why it no longer holds
 *
 * The original `subscriptions` migration said exactly that, and 2680 restated it
 * when it added `term` — *"the amount charged is the vendor's record and the
 * price is the registry's"*. That was true while the **gateway** was the only
 * thing that needed to know a price: it holds the subscription, its webhooks are
 * the source of truth (2056), and a registry edit therefore cannot reprice
 * anybody. It is still true of the charge, and nothing here touches the charge.
 *
 * What it missed is that we also **quote** a price back to people who already
 * bought. `RenewalReminders` names the amount of the coming charge in a statutory
 * notice, and it reads the registry — today's figure. The owner's ruling of
 * 2026-08-13 (3443) is that an existing customer keeps the price they signed up
 * at for ever, so the day a price moves those two numbers diverge **by design**:
 * the gateway charges the old one correctly and the notice quotes the new one,
 * and each half looks right on its own screen (3444). A grandfathered price
 * stopped being a fact about the vendor's subscription on that day and became a
 * fact about the customer's agreement — which is a row in this schema, not a row
 * in theirs.
 *
 * ## Four columns, all-or-nothing
 *
 * Integer cents plus a currency code (`18` §Money handling), the same as every
 * other amount here. The per-location rate is stored beside the plan price
 * because 3443 covers it too: a grandfathered tenant who adds a location later is
 * owed the add-on rate they signed up at, and a stored total could not express
 * that. The count is stored with them so the total is reconstructable — two rates
 * and no count is a pair of numbers nothing can add up.
 *
 * ⚠️ **THE `subscriptions_agreed_price_is_whole` CHECK IS THE POINT OF THE
 * GROUP.** `CLAUDE.md`'s writerless-column shape has eighteen instances, and the
 * form it takes with money is worse than usual: a half-written group renders a
 * plausible zero, or a bare number with no currency, on a page about a charge.
 * All four or none, refused at the database, so there is no such row to read.
 *
 * ⚠️ **NULLABLE, WITH NO BACKFILL, AND THAT IS AN ARGUED CALL** (3530–3534). A
 * `pending_checkout` row is on no price at all — the argument 2680 already made
 * for not defaulting `term` to `monthly` — so a blanket backfill would write an
 * agreement nobody made. And a backfill `UPDATE` here would match **zero rows and
 * report success**: this table is `FORCE ROW LEVEL SECURITY` and a migration runs
 * with no `app.business_id` set. Rows predating this column fall back to the
 * registry, which is what every surface does today, so the fallback is exactly
 * the present behaviour rather than a new risk — see
 * `PlanCharges::agreedPriceFor()` for what it costs and when it stops being free.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            /*
             * What one location on this term cost on the day this subscription
             * was created — not what it costs now.
             *
             * ⚠️ NOT THE TOTAL. The total is this plus the per-location rate
             * times the count, and storing the total instead would lose the
             * add-on rate that 3443 also grandfathers.
             */
            $table->unsignedInteger('price_cents')->nullable();

            /* Each location beyond the first, on the same day and the same terms. */
            $table->unsignedInteger('additional_location_cents')->nullable();

            /*
             * How many of those were sold.
             *
             * ⚠️ ZERO IS A REAL ANSWER HERE AND IS NOT THE SAME AS NULL. Every
             * subscription this application can currently sell carries zero
             * additional locations, and a stored `0` says so; null says nothing
             * was ever recorded. `RenewalReminders` deliberately did no location
             * arithmetic for exactly that reason, and this column is what lets it
             * stop guessing.
             */
            $table->unsignedSmallInteger('additional_locations')->nullable();

            /*
             * ISO 4217, uppercase. `char(3)` like `businesses.currency` and
             * `message_cost_entries.currency`.
             *
             * ⚠️ NAMED `price_currency` RATHER THAN `currency`, WHICH DEPARTS
             * FROM THOSE TWO ON PURPOSE. A subscription belongs to a business
             * that has a `currency` of its own one join away, and multi-currency
             * is in scope (2058) — so a bare `currency` on this row would read as
             * "the tenant's currency" at half its call sites. This one is the
             * currency the two amounts above are denominated in and nothing else.
             */
            $table->char('price_currency', 3)->nullable();
        });

        /*
         * ⛔ ALL FOUR OR NONE.
         *
         * A price with no currency is not a money value (`18` §Money handling,
         * and `Money::of()` refuses to construct one). A count with no rate, or a
         * rate with no count, is a total nothing can compute. And a `0` rendered
         * from a half-written group is the failure this project keeps meeting:
         * not an error, a plausible number on a page about somebody's bill.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE subscriptions
                ADD CONSTRAINT subscriptions_agreed_price_is_whole
                CHECK (
                    (
                        price_cents IS NULL
                        AND additional_location_cents IS NULL
                        AND additional_locations IS NULL
                        AND price_currency IS NULL
                    )
                    OR (
                        price_cents IS NOT NULL
                        AND additional_location_cents IS NOT NULL
                        AND additional_locations IS NOT NULL
                        AND price_currency IS NOT NULL
                    )
                )
        SQL);

        /*
         * ⚠️ POSTGRES HAS NO UNSIGNED INTEGER, so `unsignedInteger()` above is a
         * plain `integer` and the word is documentation rather than enforcement.
         * A negative agreed price is a discount nobody authorised —
         * `PlanSelection` already refuses the arithmetic that produces one
         * (`locations - 1`), and this is the same refusal where a hand-written
         * UPDATE can still reach it.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE subscriptions
                ADD CONSTRAINT subscriptions_agreed_price_is_not_negative
                CHECK (
                    (price_cents IS NULL OR price_cents >= 0)
                    AND (additional_location_cents IS NULL OR additional_location_cents >= 0)
                    AND (additional_locations IS NULL OR additional_locations >= 0)
                )
        SQL);

        /*
         * The same three-letter rule `Money::of()` enforces in PHP. It is here as
         * well because the value crosses the boundary in both directions: a row
         * carrying `'usd'` or `'US'` would be readable and would then blow up in
         * `Money::of()` at whatever screen happened to render it next, rather
         * than at the write that made it wrong.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE subscriptions
                ADD CONSTRAINT subscriptions_agreed_price_currency_is_iso
                CHECK (price_currency IS NULL OR price_currency ~ '^[A-Z]{3}$')
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_agreed_price_currency_is_iso');
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_agreed_price_is_not_negative');
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_agreed_price_is_whole');

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn([
                'price_cents',
                'additional_location_cents',
                'additional_locations',
                'price_currency',
            ]);
        });
    }
};

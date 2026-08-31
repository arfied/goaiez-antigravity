<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The annual term, and the instalment plan that collects it (decision 2680).
 *
 * ⚠️ **THREE COLUMNS AND NOT ONE MONEY COLUMN AMONG THEM.** `subscriptions` has
 * carried "no money columns here: prices live in Stripe" since it was created,
 * and that survives a second gateway: the amount charged is the vendor's record
 * and the *price* is the registry's (689). What is missing without these is not
 * an amount, it is **which of the two prices a row is on** — a fact nothing in
 * this schema could express, so every subscription read as monthly.
 *
 * ⚠️ **NULLABLE WITH NO DEFAULT AND NO BACKFILL, ON THE `gateway` COLUMN'S OWN
 * REASONING.** Defaulting `term` to `monthly` would say that every existing row
 * is on the monthly price, and most of them are `pending_checkout` rows that are
 * on no price at all. Null means "no term yet", which is exactly what
 * `pending_checkout` means, and the CHECKs below are written to accept it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            // `App\Enums\BillingTerm`. A string cast to a backed enum, never a
            // database enum (`CLAUDE.md`).
            $table->string('term')->nullable();

            /*
             * How many payments the annual price is collected in.
             *
             * ⚠️ THE COUNT, NEVER THE AMOUNTS. Decision 2055 is a rule about
             * where a number lives: the per-payment cents are derived from the
             * annual price every time they are needed and stored nowhere, because
             * two stored numbers that must sum to a third is the trap where
             * whichever is wrong is invisible. The count is not one of those — it
             * is what was sold, it cannot be derived from anything, and both
             * vendors fix it at creation.
             */
            $table->unsignedSmallInteger('instalment_payments')->nullable();

            /*
             * The last day the annual term covers.
             *
             * ⚠️ IT EXISTS BECAUSE AN INSTALMENT PLAN FINISHES PAYING LONG BEFORE
             * THE YEAR IT PAID FOR ENDS. Three payments a billing cycle apart are
             * collected inside the first quarter, and Authorize.Net then reports
             * the subscription `expired` — its own word for "the schedule of
             * payments is complete". Projecting that word straight through would
             * cancel a tenant who has paid a year in full, three months in.
             * `Subscriptions::applyAuthorizeNetSubscription()` reads this column
             * to tell that case from a term that genuinely ran out, which is
             * decision 2138's move: store what we sold, so the state is provable
             * rather than inferred.
             *
             * A date rather than a timestamp, like `authorize_net_starts_on`: a
             * term ends on a day, and a time on it would make "has the year run
             * out" answer differently either side of a request's own clock.
             */
            $table->date('annual_term_ends_on')->nullable();
        });

        /*
         * ⚠️ INSTALMENTS BELONG TO THE ANNUAL PRICE AND TO NOTHING ELSE (2055).
         * "Monthly, in three instalments" is not a thing this product sells, and
         * a row saying so would be read by every future screen as an offer that
         * exists.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE subscriptions
                ADD CONSTRAINT subscriptions_instalments_are_annual
                CHECK (instalment_payments IS NULL OR term = 'annual')
        SQL);

        /*
         * ⚠️ AND AN INSTALMENT PLAN HAS AT LEAST TWO PAYMENTS. One payment is a
         * price, not a schedule; zero is a subscription nothing ever charges.
         * `Instalments::split()` refuses both already — this is the same refusal
         * where a hand-written UPDATE can still reach it.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE subscriptions
                ADD CONSTRAINT subscriptions_instalment_plan_has_payments
                CHECK (instalment_payments IS NULL OR instalment_payments >= 2)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE subscriptions
                ADD CONSTRAINT subscriptions_annual_term_end_is_annual
                CHECK (annual_term_ends_on IS NULL OR term = 'annual')
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_annual_term_end_is_annual');
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_instalment_plan_has_payments');
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_instalments_are_annual');

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn(['term', 'instalment_payments', 'annual_term_ends_on']);
        });
    }
};

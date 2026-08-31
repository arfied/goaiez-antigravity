<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The offers a plan may be sold on — T176 P1, and the home decision 2090 said
 * this figure had to have before it could exist (1152, 2149, 2680, 2754).
 *
 * ## ⛔ AN OFFER IS NOT A PLAN, AND THAT SENTENCE IS THE WHOLE SCHEMA
 *
 * `plan_entitlements` answers *what a plan grants and what it costs*, keyed by
 * `Plan`. A founder rate is **the same plan sold on different terms**, so seeding
 * it there would have made `$99.99` the price of `Plan::Base` — silently
 * repricing retail for every tenant who signs up after the founder window closes.
 * 2090 refused exactly that and named this table as the place it belongs. The
 * refusal held for four days across three slices; this is the table.
 *
 * ## ⚠️ THERE IS NO `retail` ROW, AND ITS ABSENCE IS THE DESIGN
 *
 * The obvious shape is one row per offer including the standing one, and it is
 * the wrong one: the retail schedule already lives in `plan_entitlements`, so a
 * `retail` row here would be decision 754's trap — two seeded numbers that are
 * supposed to agree, whichever is wrong invisible, and the one people act on
 * whichever the page happens to read. **No live offer means the registry's
 * price**, which is what every caller did before this table existed and is what
 * every caller does again the moment the window closes.
 *
 * ## Platform-scoped, no row-level security, and the argument is `PlanEntitlement`'s
 *
 * These are *our* prices, not a tenant's data. Nothing on the row names a
 * business, and a tenant-owned offer table would mean each business editing the
 * price it is billed at. It joins the named-exception list in
 * `tests/Feature/Architecture/TenancyTest.php` — both of them, since the model
 * carries no tenancy trait either — with the argument written there as well as
 * here. What replaces the scope is **two** things: a chokepoint lint naming
 * `App\Services\Billing\PlanOffers` as the only reader and writer, and the
 * model's own refusal to be deleted.
 *
 * ⚠️ **THIS SAID "THREE" AND NAMED AN ADMIN GATE THAT DOES NOT EXIST (4348).**
 * 4334 refused an admin surface for offers outright — one offer exists and R18's
 * flip happens once — so there is no screen for a gate to sit on. Counting it
 * made the count read as reassuring in four places at once (314–316: a docblock
 * claiming three layers of enforcement is what stops the next reviewer looking).
 * The one console path that does reach the table, `offers:close`, is protected
 * by shell access rather than by a gate.
 *
 * ## The window, and the one figure that is deliberately open-ended
 *
 * T176 R18 ends the founder window at *"the SEO + sites flip event — a dated,
 * announced flip"*, and that event has no date. So `closes_at` is nullable and
 * the seeded founder rows carry null: the window is open until somebody names
 * the day. ⚠️ **That is fail-OPEN on a price**, which this codebase normally
 * refuses — see the decisions block for why it is the right reading here and
 * what it costs, and note that closing the window is a one-row edit that nothing
 * in this application will ever remind anybody to make.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_offers', function (Blueprint $table): void {
            $table->id();

            /*
             * Which offer this row belongs to — `founder`, and nothing else yet.
             *
             * A plain string rather than a PHP backed enum cast, which is the one
             * place this table departs from this codebase's habit, and it departs
             * *toward* the same goal. The rule that produced the enum convention
             * is "never a database enum, because a DB enum is a second source of
             * truth that drifts from the PHP one" — there is no PHP enum here to
             * drift from. The set of offers is data; an enum of offer names would
             * mean a code deploy to run a promotion, and the seeded catalogue is
             * what names them.
             */
            $table->string('key');

            /*
             * Which of the two prices this row states. Cast to `BillingTerm` in
             * the model — a string column, never a database enum (`CLAUDE.md`).
             *
             * ⚠️ ONE ROW PER TERM RATHER THAN FOUR COLUMNS ON ONE ROW. An offer
             * that priced only the annual term would otherwise carry two null
             * monthly columns, and a null price is indistinguishable from a price
             * of zero at the reader that formats it.
             */
            $table->string('term');

            /* What one location costs on this term, under this offer. */
            $table->unsignedInteger('price_cents');

            /* Each location beyond the first, on the same terms. */
            $table->unsignedInteger('additional_location_cents');

            /*
             * ISO 4217, uppercase — `subscriptions.price_currency`'s shape and
             * its naming argument: an offer has a currency of its own and it is
             * not "the tenant's currency", which is a different fact a join away.
             */
            $table->char('price_currency', 3);

            /*
             * How many payments this offer's term is collected in, when it is
             * bought in instalments. NULL means this term is not offered in
             * instalments at all.
             *
             * ⛔ **THIS COLUMN IS 2754's INSTRUCTION AND THE ANSWER TO T176's OWN
             * COLLISION.** `PlanCharges::PAYMENTS` is **three** — `CLAUDE.md`'s
             * retail annual, 33233 / 33233 / 33234 — and R10's founder annual is
             * **two**. Both are true, of different products. 2754 said in advance
             * that whoever built this table moves the count onto the offer rather
             * than adding a second constant beside it, and this is that column.
             * The retail count stays where it is, because there is deliberately no
             * retail row here to put it on.
             *
             * ⚠️ **THE PER-PAYMENT CENTS ARE NEVER STORED** (2055, 2092). They are
             * derived from the annual figure by `App\Support\Instalments` every
             * time they are needed; two seeded numbers that must sum to a third is
             * the trap 2055 exists to close, and 2092 notes that the two founder
             * rows prove the rule between them — the add-on divides evenly and the
             * base does not.
             */
            $table->unsignedSmallInteger('instalment_payments')->nullable();

            /*
             * When this offer starts being quoted.
             *
             * ⚠️ `timestamp`, NOT `timestampTz`. The session is pinned to UTC so
             * the two behave identically, and `Architecture\TimeTest` fails the
             * build on a new `timestampTz` — 278 of this schema's datetime columns
             * are `timestamp` and the nine that are not went unnoticed for a week,
             * because a mixed schema is what let a machine's timezone reach the
             * data at all. This migration was written with `timestampTz` and the
             * lint caught it, which is what the lint is for.
             */
            $table->timestamp('opens_at');

            /* When it stops. NULL is an open-ended window — see the docblock. */
            $table->timestamp('closes_at')->nullable();

            $table->timestamps();

            /*
             * One statement of one offer's price on one term. A second row for
             * the same pair would make "what does the founder annual cost" a
             * question with two answers and an arbitrary tiebreak.
             */
            $table->unique(['key', 'term']);

            /*
             * The read is "which offers are live right now", on every quote and on
             * the LCP-gated marketing home. Both window columns, so the planner
             * has an index rather than a sequential scan on the page row 1's gate
             * is measured against.
             */
            $table->index(['opens_at', 'closes_at']);
        });

        /*
         * ⛔ A PRICE OF ZERO IS NOT AN OFFER, IT IS A FREE PLAN.
         *
         * `Plan::Free` exists and is a different thing entirely. An offer row
         * pricing the base plan at nothing would hand every signup inside the
         * window a plan nobody is billed for, and every screen would render it
         * perfectly — `Money::of(0)` formats as `$0` without complaint. Strictly
         * positive rather than non-negative, because unlike `subscriptions` (where
         * a null group means "no agreement recorded") every row here is a live
         * statement that something costs something.
         *
         * ⚠️ POSTGRES HAS NO UNSIGNED INTEGER, so `unsignedInteger()` above is a
         * plain `integer` and this is the enforcement rather than the word.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE plan_offers
                ADD CONSTRAINT plan_offers_prices_are_positive
                CHECK (price_cents > 0 AND additional_location_cents > 0)
        SQL);

        /*
         * `Money::of()`'s three-letter rule, at the boundary the value crosses in
         * both directions — `subscriptions_agreed_price_currency_is_iso`'s reason
         * one table over: a row carrying `'usd'` reads fine and then throws at
         * whatever screen renders it next, rather than at the write that made it
         * wrong.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE plan_offers
                ADD CONSTRAINT plan_offers_currency_is_iso
                CHECK (price_currency ~ '^[A-Z]{3}$')
        SQL);

        /*
         * ⛔ ONE INSTALMENT IS NOT AN INSTALMENT PLAN, AND ZERO IS NOT A NUMBER OF
         * PAYMENTS.
         *
         * `Instalments::split()` already refuses a count below one, so the value
         * this catches is `1` — which passes every PHP guard, produces a
         * one-element schedule, and sells an "instalment plan" that takes the
         * whole annual price in a single charge. That is a misrepresentation on
         * the disclosure this application renders to comply with California's
         * Automatic Renewal Law, not merely a wrong number.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE plan_offers
                ADD CONSTRAINT plan_offers_instalments_are_plural
                CHECK (instalment_payments IS NULL OR instalment_payments >= 2)
        SQL);

        /*
         * A window that closes before it opens is a window nothing is ever
         * offered in — and the failure is silent, because "no live offer" is a
         * legitimate state that resolves to the retail price. A typo in a date
         * would therefore look exactly like a promotion that has ended.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE plan_offers
                ADD CONSTRAINT plan_offers_window_is_ordered
                CHECK (closes_at IS NULL OR closes_at > opens_at)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_offers');
    }
};

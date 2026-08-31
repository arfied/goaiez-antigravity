<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The two dates a trial needs, and the invariant that keeps `active` honest
 * (row 22, slice A).
 *
 * ⚠️ **THE STRIPE *CUSTOMER* COLUMNS ARE NOT HERE, AND THEIR ABSENCE IS THE
 * RULE BEING APPLIED RATHER THAN AN OVERSIGHT.** The first draft of this
 * migration added `stripe_id`, `pm_type` and `pm_last_four` to `businesses` and
 * put Cashier's `ManagesCustomer` on the model. Nothing in slice A can fill any
 * of them — Checkout and the customer create are slice B — so that would have
 * been three columns and a trait with no writer, which is decision 272's shape
 * committed inside the slice whose own service docblock counts it to twelve.
 * They land with `BillingCheckout`, in the slice that calls Stripe.
 *
 * **What slice A did settle, so that slice B does not have to re-open it:**
 * Cashier's own subscription storage is a table also called `subscriptions`,
 * with a different shape from DATA-MODEL §5.12's — which this schema has carried
 * since Stage 0, tenant-owned with RLS, and which `Api\MeController` reads.
 * Publishing Cashier's migration would collide on the table name outright. So
 * Cashier is adopted for the **customer half only** (`ManagesCustomer`,
 * `ManagesPaymentMethods`, `ManagesInvoices`) and never `ManagesSubscriptions`,
 * and Stripe's subscription state is projected onto our row by slice B's webhook
 * handlers. That is the reconciliation the Stage 0 migration deferred to "the
 * billing ticket's decision".
 *
 * No money columns are added here. Prices live in the registry (CFG1) and in
 * Stripe; nothing about a subscription row needs to restate one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            // When the trial ends. Distinct from current_period_end, which is
            // when the *paid* period ends — they coincide exactly once, at the
            // first charge, and conflating them is how a tenant gets billed on
            // the wrong day.
            $table->timestamp('trial_ends_at')->nullable();

            // Set when Stripe reports the subscription gone, so that "cancelled
            // last Tuesday" survives the row being reused if they resubscribe.
            $table->timestamp('ends_at')->nullable();
        });

        /*
         * ⚠️ A SUBSCRIPTION MAY NOT CLAIM TO BE PAYING WITH NOTHING BEHIND IT.
         *
         * `active` and `past_due` both assert that Stripe has a live
         * subscription for this business — one paying, one being retried. Either
         * without a `stripe_subscription_id` is a row that entitles a tenant to
         * the whole product on the strength of a string somebody typed.
         *
         * This is decision 359's ruling applied again: the boundary rested on
         * one runtime read while `$guarded` left `status` mass-assignable, so
         * the claim and the constraint land together. The CHECK is what catches
         * the repair script and the seeder that reach neither the enum nor the
         * service (decision 216's three-layer reasoning) — and slice B's webhook
         * handler will be a fourth writer-shaped thing with its own bugs.
         *
         * ⚠️ `trialing` IS DELIBERATELY PERMITTED WITH NO STRIPE ID. That is
         * precisely the state provisioning writes today, before any card is
         * collected; forbidding it would make the honest state unrepresentable
         * and push the write toward a dishonest one. Slice B tightens this when
         * a trial genuinely implies a Stripe customer.
         *
         * `status IS NULL` is permitted for the rows that predate this slice.
         * There are none in production — nothing ever wrote this table — but a
         * CHECK that cannot be added to an existing table is a CHECK that gets
         * dropped instead of fixed.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE subscriptions
                ADD CONSTRAINT subscriptions_paying_status_has_stripe_subscription
                CHECK (
                    status IS NULL
                    OR status NOT IN ('active', 'past_due')
                    OR stripe_subscription_id IS NOT NULL
                )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_paying_status_has_stripe_subscription');

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn(['trial_ends_at', 'ends_at']);
        });
    }
};

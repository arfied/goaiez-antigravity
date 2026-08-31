<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The second gateway's storage (decision 2056, T137 R2/SL-11).
 *
 * ⚠️ **ADDITIVE, NOT A MIGRATION AWAY FROM STRIPE.** The owner's answer was
 * *"we are adding authorize.net as our second billing provider"*, so nothing
 * Stripe-shaped is dropped, renamed or repointed here. `subscriptions` gains a
 * `gateway` column and three Authorize.Net ids beside the Stripe ones, and every
 * existing row keeps its meaning.
 *
 * ⚠️ **THE TWO CHECK CONSTRAINTS FROM SLICE B WOULD HAVE MADE THIS GATEWAY
 * UNUSABLE, SILENTLY AT FIRST (decision 2139).** They read
 * `status <> 'trialing' OR stripe_subscription_id IS NOT NULL` and
 * `status <> 'pending_checkout' OR stripe_subscription_id IS NULL`. An
 * Authorize.Net trial has an ARB subscription id and **no Stripe id at all**, so
 * the first constraint refuses every Authorize.Net signup at the moment of the
 * insert — and the second one is worse in the opposite direction: it would be
 * *satisfied* by a row sitting at `pending_checkout` with a live ARB
 * subscription running behind it, which is a tenant being charged while this
 * application reports they have no subscription. **Both are replaced with the
 * gateway-agnostic form**, which is the same rule stated over "a subscription id
 * from whichever gateway this row is on".
 *
 * ⚠️ **THE REPLACEMENT IS NOT A RELAXATION.** The old constraint could see one
 * column; the new one sees the column that matches the row's own gateway, which
 * is strictly more specific. A row on Stripe with an Authorize.Net id and no
 * Stripe id passed the old CHECK and fails the new one.
 *
 * Both new platform-scoped tables exist for decision 682's reason, unchanged: a
 * webhook arrives with no tenant, `businesses` and `subscriptions` are
 * `ENABLE`+`FORCE` RLS, so a handler with no session tenant reads **zero rows
 * rather than an error**. The vendor id maps to a business id here, the handler
 * calls `Tenancy::actingAs()`, and every line after that is ordinarily scoped.
 * **No policy on any tenant-owned table changes.**
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * Authorize.Net customer profile → business. The index that makes a
         * tenantless webhook resolvable, on `stripe_customers`' precedent.
         *
         * ⚠️ IT INDEXES THE **CUSTOMER PROFILE**, NOT THE SUBSCRIPTION, AND THAT
         * IS THE CHOICE THAT MATTERS. Authorize.Net's subscription webhooks name
         * the subscription and — depending on the event — not always the
         * profile, while every profile and payment-profile event names the
         * profile. Indexing the longer-lived object means one row answers for
         * every event about that customer, including the ones that arrive before
         * a subscription exists.
         */
        Schema::create('authorize_net_customers', function (Blueprint $table): void {
            // The vendor's own id as the primary key, like `stripe_customers`.
            // There is no second identity to mint: the row exists only to answer
            // "whose profile is this".
            $table->string('authorize_net_customer_profile_id')->primary();

            // ⚠️ RESTRICTED, NOT CASCADING, for `stripe_customers`' reason: a
            // business row disappearing while a live ARB subscription still
            // points at it means an inbound event that resolves to nothing,
            // silently, on the vendor that takes money. Deleting a business has
            // to cancel the subscription first, deliberately, and nothing does
            // that yet.
            $table->foreignId('business_id')->unique()->constrained()->restrictOnDelete();

            /*
             * ⚠️ THE SUBSCRIPTION ID IS HERE AS WELL, NULLABLE, AND IT IS AN
             * INDEX RATHER THAN A SECOND SOURCE OF TRUTH — the same relationship
             * `stripe_customers.stripe_customer_id` has to `subscriptions`.
             *
             * It has to be: `net.authorize.customer.subscription.failed` and its
             * siblings identify the subscription and, in the shapes observed,
             * not always the profile. Without this column those events resolve
             * to no tenant at all, which is the one class of event that must
             * never be lost — a failed payment nobody hears about is a tenant
             * who silently stops being billed.
             *
             * If it ever disagrees with the tenant-owned row, **the tenant-owned
             * row is the authority and this is the stale index**. Stated here
             * because the opposite guess is the natural one for whoever finds
             * the mismatch.
             */
            $table->string('authorize_net_subscription_id')->nullable()->unique();

            $table->timestamp('created_at');
        });

        DB::statement('ALTER TABLE authorize_net_customers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE authorize_net_customers FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY platform_scoped ON authorize_net_customers
                FOR ALL USING (true) WITH CHECK (true)
        SQL);

        /*
         * Every Authorize.Net webhook we have accepted, so that a redelivery
         * changes nothing.
         *
         * ⚠️ THE DEDUPLICATION KEY IS THE PAYLOAD'S OWN `notificationId`, WHICH
         * IS NOT THE SAME PROMISE STRIPE MAKES. Stripe documents duplicate
         * delivery and a three-day retry window; Authorize.Net publishes no
         * retry schedule at all. That makes the claim row *more* important
         * rather than less: with no documented redelivery behaviour, "did we
         * already do this" cannot be answered by reasoning about the vendor, so
         * it has to be answered by a row.
         *
         * ⚠️ NO PAYLOAD COLUMN, IN EITHER DIRECTION — `stripe_events`' rule
         * (593). A copy of every billing event is a second store of customer and
         * card metadata, never rotated and never read.
         */
        Schema::create('authorize_net_events', function (Blueprint $table): void {
            $table->id();

            // UNIQUE is the whole mechanism: the handler INSERTs and treats a
            // conflict as "already done", rather than SELECTing first — decision
            // 350's lesson, where a check-then-insert held only sequentially.
            $table->string('notification_id')->unique();

            $table->string('type');

            // What we did with it. `App\Enums\GatewayEventOutcome` — the same
            // vocabulary `stripe_events` uses, which is why decision 2130
            // renamed that enum rather than copying it. A string cast to a
            // backed enum, never a database enum (`CLAUDE.md`).
            $table->string('outcome');

            $table->timestamp('received_at');
        });

        DB::statement('ALTER TABLE authorize_net_events ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE authorize_net_events FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY platform_scoped ON authorize_net_events
                FOR ALL USING (true) WITH CHECK (true)
        SQL);

        Schema::table('subscriptions', function (Blueprint $table): void {
            /*
             * ⚠️ NULLABLE WITH NO DEFAULT, AND THAT IS NOT LAZINESS.
             *
             * Defaulting to `stripe` would say that every row already on this
             * table is a Stripe subscription, and most of them are
             * `pending_checkout` rows with no gateway at all — a person who
             * registered and never paid is on neither. Backfilling them to
             * `stripe` would make the CHECK below assert something untrue about
             * them, and it would also quietly decide that an existing tenant who
             * *does* pay must pay through Stripe.
             *
             * Null means "no gateway yet", which is exactly what
             * `pending_checkout` means, and the CHECK below is written to accept
             * it.
             */
            $table->string('gateway')->nullable();

            $table->string('authorize_net_customer_profile_id')->nullable();
            $table->string('authorize_net_payment_profile_id')->nullable();
            $table->string('authorize_net_subscription_id')->nullable();

            /*
             * The watermark, for the same reason `stripe_synced_at` exists.
             *
             * ⚠️ AND IT CANNOT BE THE SAME COLUMN. Both gateways may have
             * touched one business over its life — 2056 keeps Stripe live — and
             * one watermark shared between two vendors' clocks would let a
             * stale event from the gateway a tenant left suppress a live one
             * from the gateway they are on.
             */
            $table->timestamp('authorize_net_synced_at')->nullable();

            /*
             * When the first charge is due — the trial's end, expressed as the
             * ARB `startDate` we sent.
             *
             * ⚠️ IT EXISTS BECAUSE AUTHORIZE.NET HAS NO TRIAL STATUS AND NO WAY
             * TO REPORT ONE (decision 2138). `trial_ends_at` already holds
             * Stripe's own `trial_end` projected back; on this gateway there is
             * nothing to project, because a trial here is expressed as a future
             * start date and the vendor never calls it a trial. This column is
             * what we sent, so `trialing` on an Authorize.Net row is a fact we
             * can still prove rather than one we infer.
             */
            $table->date('authorize_net_starts_on')->nullable();
        });

        /*
         * ⚠️ THE OLD CONSTRAINTS COME OUT AND GATEWAY-AGNOSTIC ONES GO IN. See
         * the class docblock: as written they refuse every Authorize.Net trial
         * and accept a live Authorize.Net subscription being reported as no
         * subscription at all.
         */
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_trialing_has_stripe_subscription');
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_pending_checkout_has_no_subscription');

        /*
         * ⚠️ AND A THIRD ONE, FROM AN EARLIER MIGRATION AGAIN, WHICH THIS SLICE
         * MISSED UNTIL ITS OWN TESTS FOUND IT (decision 2147).
         *
         * `subscriptions_paying_status_has_stripe_subscription` came in with the
         * trial dates and reads `status NOT IN ('active','past_due') OR
         * stripe_subscription_id IS NOT NULL`. It refuses **every paying
         * Authorize.Net subscription** — the state the gateway spends its whole
         * life in — and it refuses `past_due`, which is the state a declined
         * payment writes, so dunning could not have started either.
         *
         * ⚠️ **WORTH RECORDING AS A MISS RATHER THAN A TIDY-UP.** Two of these
         * were found by reading the slice B migration and the third was not,
         * because it lives in a migration named for trial *dates*. The lesson is
         * the one `CLAUDE.md` already states about a table's constraints being
         * spread across the migrations that happened to add them: grep
         * `pg_constraint`, not the file you expect.
         */
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_paying_status_has_stripe_subscription');

        DB::statement(<<<'SQL'
            ALTER TABLE subscriptions
                ADD CONSTRAINT subscriptions_paying_status_has_gateway_subscription
                CHECK (
                    status IS NULL
                    OR status NOT IN ('active', 'past_due')
                    OR stripe_subscription_id IS NOT NULL
                    OR authorize_net_subscription_id IS NOT NULL
                )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE subscriptions
                ADD CONSTRAINT subscriptions_trialing_has_gateway_subscription
                CHECK (
                    status IS NULL
                    OR status <> 'trialing'
                    OR stripe_subscription_id IS NOT NULL
                    OR authorize_net_subscription_id IS NOT NULL
                )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE subscriptions
                ADD CONSTRAINT subscriptions_pending_checkout_has_no_subscription
                CHECK (
                    status IS NULL
                    OR status <> 'pending_checkout'
                    OR (stripe_subscription_id IS NULL AND authorize_net_subscription_id IS NULL)
                )
        SQL);

        /*
         * ⚠️ AND THE CONSTRAINT THAT DID NOT EXIST BEFORE, BECAUSE THERE WAS
         * ONLY ONE GATEWAY TO CONFUSE.
         *
         * A row must not carry ids from both vendors, and its `gateway` must
         * match the ids it carries. Without this, a subscription "on Stripe"
         * with an ARB id present is representable, and every reader that
         * branches on `gateway` then reads the wrong vendor's state — which is
         * the failure that bills somebody twice rather than the one that refuses
         * service.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE subscriptions
                ADD CONSTRAINT subscriptions_gateway_matches_its_ids
                CHECK (
                    (stripe_subscription_id IS NULL OR gateway = 'stripe')
                    AND (authorize_net_subscription_id IS NULL OR gateway = 'authorize_net')
                )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_gateway_matches_its_ids');
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_pending_checkout_has_no_subscription');
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_trialing_has_gateway_subscription');
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_paying_status_has_gateway_subscription');

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn([
                'gateway',
                'authorize_net_customer_profile_id',
                'authorize_net_payment_profile_id',
                'authorize_net_subscription_id',
                'authorize_net_synced_at',
                'authorize_net_starts_on',
            ]);
        });

        // ⚠️ THE ROLLBACK RESTORES SLICE B's CONSTRAINTS EXACTLY, INCLUDING THE
        // HOLE. `down()` puts the schema back the way it was; it is not the
        // place to keep an improvement, because a half-rolled-back schema is
        // harder to reason about than the one that was there before. `29`
        // §12.1's migration-down test is what makes this path run at all.
        DB::statement(<<<'SQL'
            ALTER TABLE subscriptions
                ADD CONSTRAINT subscriptions_trialing_has_stripe_subscription
                CHECK (
                    status IS NULL
                    OR status <> 'trialing'
                    OR stripe_subscription_id IS NOT NULL
                )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE subscriptions
                ADD CONSTRAINT subscriptions_pending_checkout_has_no_subscription
                CHECK (
                    status IS NULL
                    OR status <> 'pending_checkout'
                    OR stripe_subscription_id IS NULL
                )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE subscriptions
                ADD CONSTRAINT subscriptions_paying_status_has_stripe_subscription
                CHECK (
                    status IS NULL
                    OR status NOT IN ('active', 'past_due')
                    OR stripe_subscription_id IS NOT NULL
                )
        SQL);

        Schema::dropIfExists('authorize_net_events');
        Schema::dropIfExists('authorize_net_customers');
    }
};

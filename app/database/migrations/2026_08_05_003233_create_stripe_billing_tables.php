<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The two platform-scoped tables a webhook needs, and the CHECK slice A said
 * this slice would tighten (row 22, slice B).
 *
 * ⚠️ **BOTH TABLES EXIST BECAUSE A WEBHOOK ARRIVES WITH NO TENANT** (decision
 * 682). Stripe posts to a public URL with an event that names a Stripe customer
 * and nothing else. `businesses` and `subscriptions` are both `ENABLE`+`FORCE`
 * row-level security on `app.business_id`, so a handler with no session tenant
 * reads **zero rows rather than an error** — and `withoutGlobalScopes()` does
 * not help, because RLS sits beneath the application scope (569). Three earlier
 * screens met the same wall and all three refused to widen the boundary,
 * serving the question from a store that already has no tenant (569, 620, 626).
 * This is the fourth, answered the same way: the vendor id maps to a business
 * id here, the handler calls `Tenancy::actingAs()`, and every line after that is
 * ordinarily scoped. **No policy on any tenant-owned table changed.**
 *
 * Both take RLS `ENABLE`+`FORCE` with a permissive policy, on
 * `impersonation_sessions`' precedent (562): the flags say out loud that the
 * table was considered rather than forgotten, and the policy says the openness
 * is deliberate. What protects these two is that neither holds anything a
 * tenant could be harmed by — one is an opaque vendor id, the other is a list of
 * event ids we have already seen.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * Stripe customer → business. The index that makes a tenantless webhook
         * resolvable.
         *
         * ⚠️ THE STRIPE CUSTOMER ID IS *ALSO* ON `subscriptions`, AND THAT IS
         * NOT TWO SOURCES OF TRUTH — it is one fact and one index of it, in the
         * same relationship a unique index has to its column. `Subscriptions`
         * writes both inside one transaction and nothing else writes either, so
         * they cannot drift; the split exists because the authoritative copy
         * lives behind RLS and the lookup has to happen before a tenant exists.
         * If they ever do disagree, the tenant-owned row is the authority and
         * this one is the stale index — stated here because the opposite guess
         * is the natural one for whoever finds the mismatch.
         */
        Schema::create('stripe_customers', function (Blueprint $table): void {
            // Stripe's own id as the primary key. There is no second identity
            // to mint: the row exists only to answer "whose customer is this",
            // and a surrogate id would be a column nothing ever selects by.
            $table->string('stripe_customer_id')->primary();

            // ⚠️ RESTRICTED, NOT CASCADING, and for a different reason from
            // `impersonation_sessions`: a business row disappearing while a
            // live Stripe customer still points at it means an inbound
            // subscription event that resolves to nothing, silently, on the
            // vendor that takes money. Deleting a business has to cancel the
            // subscription first, deliberately, and nothing does that yet.
            $table->foreignId('business_id')->unique()->constrained()->restrictOnDelete();

            $table->timestamp('created_at');
        });

        DB::statement('ALTER TABLE stripe_customers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE stripe_customers FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY platform_scoped ON stripe_customers
                FOR ALL USING (true) WITH CHECK (true)
        SQL);

        /*
         * Every Stripe event we have accepted, so that a redelivery changes
         * nothing.
         *
         * ⚠️ STRIPE DELIVERS DUPLICATES AND DELIVERS OUT OF ORDER, BOTH BY
         * DOCUMENTED DESIGN — its own webhook guidance says to log event ids and
         * skip ones already seen, and that "Stripe doesn't guarantee the
         * delivery of events in the order that they're generated". Retries run
         * for up to three days.
         *
         * ⚠️ NO PAYLOAD COLUMN, IN EITHER DIRECTION. The same argument as
         * `credential_changes` (593): a copy of every billing event is a second
         * store of customer and card metadata, never rotated and never read,
         * and it would be the only place in this schema holding a vendor's
         * description of somebody's payment method. The event id and its type
         * are what idempotency needs; nothing else here is evidence of anything
         * Stripe cannot re-answer.
         */
        Schema::create('stripe_events', function (Blueprint $table): void {
            $table->id();

            // Stripe's `evt_…`. UNIQUE is the whole mechanism: the handler
            // INSERTs and treats a conflict as "already done", rather than
            // SELECTing first — decision 350's lesson, where a check-then-insert
            // held only sequentially and two simultaneous posts both inserted.
            // Two concurrent redeliveries of one event are exactly that race.
            $table->string('stripe_event_id')->unique();

            $table->string('type');

            // What we did with it. `App\Enums\GatewayEventOutcome` — a string
            // cast to a backed enum, never a database enum (CLAUDE.md).
            //
            // Recorded rather than inferred from absence, because "we ignored
            // this type on purpose" and "this was never delivered" are different
            // answers to the only question anybody asks of this table.
            $table->string('outcome');

            $table->timestamp('received_at');
        });

        DB::statement('ALTER TABLE stripe_events ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE stripe_events FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY platform_scoped ON stripe_events
                FOR ALL USING (true) WITH CHECK (true)
        SQL);

        /*
         * ⚠️ THE BACKFILL HAS TO SUSPEND `FORCE` FOR THE LENGTH OF ONE
         * STATEMENT, AND DECISION 319 IS WHY IT WOULD OTHERWISE BE A NO-OP.
         *
         * 319 records that a migration reading a tenant-owned table with no
         * `app.business_id` set sees **zero rows, not an error** — the policy
         * compares `business_id` to NULL and nothing matches. A migration runs
         * as the table owner, and `FORCE` is precisely what makes the owner
         * subject to that. So the obvious UPDATE below would report success,
         * touch nothing, and the CHECK added after it would then fail the
         * deployment — which reads as a broken migration rather than as
         * unmigrated data, sending whoever is on the deploy in the wrong
         * direction entirely.
         *
         * `NO FORCE` restores the ordinary owner exemption for this transaction
         * and `FORCE` puts it back. DDL is transactional in Postgres, so a
         * failure anywhere in this migration rolls the suspension back with
         * everything else — the table cannot be left unforced by a crash.
         *
         * ⚠️ THIS IS NOT A PATTERN TO COPY FOR CONVENIENCE. It is here because
         * slice A shipped a status this slice renames, on rows real people own:
         * `subscriptions` gained its first writer today (580), so production
         * carries `trialing` rows written by registrations since that deploy,
         * and every one of them means "registered, no card" (586).
         */
        Schema::table('subscriptions', function (Blueprint $table): void {
            /*
             * ⚠️ THE WATERMARK THAT MAKES OUT-OF-ORDER DELIVERY DETECTABLE.
             *
             * Stripe states plainly that it "doesn't guarantee the delivery of
             * events in the order that they're generated", and the damaging
             * reordering is specific: a `customer.subscription.updated` from
             * before a cancellation arriving after it, which reinstates a
             * subscription that has ended. Every event carries the object as a
             * full snapshot and no version, so there is nothing *in* a payload
             * to compare — the only orderable value is the event's own `created`
             * timestamp, and comparing it needs somewhere to remember the last
             * one applied.
             *
             * Nullable, because every row that exists today predates this and
             * has had no event applied to it. A null watermark accepts the first
             * event, which is correct: there is nothing it could be older than.
             */
            $table->timestamp('stripe_synced_at')->nullable();
        });

        DB::statement('ALTER TABLE subscriptions NO FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            UPDATE subscriptions
               SET status = 'pending_checkout'
             WHERE status = 'trialing'
               AND stripe_subscription_id IS NULL
        SQL);

        DB::statement('ALTER TABLE subscriptions FORCE ROW LEVEL SECURITY');

        /*
         * ⚠️ `trialing` NOW REQUIRES A SUBSCRIPTION BEHIND IT — the tightening
         * the slice A migration named in its own docblock: "slice B tightens
         * this when a trial genuinely implies a Stripe customer."
         *
         * The old constraint stays and this one is added beside it rather than
         * replacing it, so that the two paying statuses keep their own named
         * failure. A single merged constraint would report one name for three
         * different lies.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE subscriptions
                ADD CONSTRAINT subscriptions_trialing_has_stripe_subscription
                CHECK (
                    status IS NULL
                    OR status <> 'trialing'
                    OR stripe_subscription_id IS NOT NULL
                )
        SQL);

        /*
         * ⚠️ AND THE INVERSE, WHICH IS THE HALF AN IMPLEMENTATION FORGETS.
         * `pending_checkout` asserts that no subscription exists. A row carrying
         * that status *and* a subscription id is a subscription that is running
         * and being reported as not existing — the failure that loses money
         * rather than the one that refuses service, so it is the direction worth
         * constraining.
         *
         * ⚠️ IT BINDS THE SUBSCRIPTION ID AND DELIBERATELY NOT THE CUSTOMER ID.
         * A Stripe *customer* is created to open the Checkout Session, so it
         * exists for as long as somebody sits on Stripe's payment page — and for
         * good if they close the tab. Forbidding it here would either make that
         * ordinary state unrepresentable or push `linkStripeCustomer()` into
         * writing a status it has no evidence for, which is decision 290's
         * mistake with a card attached.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE subscriptions
                ADD CONSTRAINT subscriptions_pending_checkout_has_no_subscription
                CHECK (
                    status IS NULL
                    OR status <> 'pending_checkout'
                    OR stripe_subscription_id IS NULL
                )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_pending_checkout_has_no_subscription');
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_trialing_has_stripe_subscription');

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn('stripe_synced_at');
        });

        Schema::dropIfExists('stripe_events');
        Schema::dropIfExists('stripe_customers');
    }
};

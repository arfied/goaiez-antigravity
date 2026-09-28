<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\AuthorizeNetSubscriptionStatus;
use App\Enums\BillingTerm;
use App\Enums\LifecycleRung;
use App\Enums\PaymentGateway;
use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\AuthorizeNetCustomer;
use App\Models\Business;
use App\Models\Location;
use App\Models\StripeCustomer;
use App\Models\Subscription;
use App\Services\Config\DefaultsRegistry;
use App\Support\PlanQuote;
use App\Support\PlanSelection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * The one place `subscriptions` is written.
 *
 * ⚠️ **THE TABLE HAD NO WRITER AT ALL.** A table, a model, a factory, an RLS
 * policy, a schema isolation test, and one reader in `MeController` that
 * returned null for every tenant that has ever existed — while nothing in
 * `app/` could create a row. That is **the twelfth instance of decision 272's
 * shape**, after `Business::provision()`, `autopilot_settings` (377),
 * `feedback_pages`, `review_destinations`, `plugins` (399) and
 * `data_classification` (465). At twelve it is not worth calling a coincidence:
 * a schema slice creates a table with tests proving it is *isolated* rather than
 * *reachable*, and an isolation test passes perfectly against a table nothing
 * writes. This one happened to be the table that takes money.
 *
 * ⚠️ **WHAT A SECOND WRITER WOULD COST.** This class holds the rules about which
 * states may coexist — that a trial has an end date, that `active` requires
 * Stripe behind it, that the plan and the status move together. A screen or an
 * importer setting `status = 'active'` directly knows none of that, and the
 * consequence is a tenant entitled to the whole product with nothing billing
 * them. The database CHECK added alongside this class refuses the worst version
 * of that, and it is deliberately narrower than these rules: it can see whether
 * a Stripe id is present and cannot see whether it is the right one. An
 * `ArchitectureTest` lint keeps the writer count at one.
 *
 * ⚠️ **NOTHING HERE CALLS STRIPE, AND THAT IS STILL TRUE IN SLICE B.** This
 * class writes rows; {@see StripeApi} opens sockets and {@see BillingCheckout}
 * and {@see StripeWebhooks} decide when. What slice B added here is the other
 * end of that pipe — the customer link and the projection of a verified Stripe
 * subscription onto our row — so that the rules about which states may coexist
 * stay in the one class that has always held them.
 */
final class Subscriptions
{
    /**
     * ⚠️ **DEFAULTED RATHER THAN INJECTED, LIKE EVERY OTHER `PlanCharges` HOLDER
     * IN THIS NAMESPACE** ({@see BillingCheckout}, {@see AuthorizeNetGateway},
     * {@see RenewalReminders}). It reads the registry and holds no state, and this
     * class is resolved from the container in some places and constructed with
     * `new` in others — a required argument here would be a change to every one of
     * those call sites for no benefit.
     */
    public function __construct(
        private readonly ?DefaultsRegistry $defaults = null,
        private readonly PlanCharges $charges = new PlanCharges,
    ) {}

    /**
     * Record a newly provisioned business as registered and not yet subscribed.
     *
     * ⚠️ **THIS REPLACES SLICE A's `startTrial()`, AND THE RENAME IS THE
     * DECISION (685).** Slice A wrote `trialing` here and said at the write that
     * it meant "registered" rather than "card captured" — decision 586, which
     * pinned the null Stripe ids in a test **so that this slice would break it**.
     * It did. Now that a card is collectable, one word cannot go on meaning both
     * things: `trialing` gets Stripe's meaning back, with a CHECK requiring a
     * subscription behind it, and the honest pre-Checkout state gets its own
     * name in {@see SubscriptionStatus::PendingCheckout}.
     *
     * ⚠️ **NO TRIAL DATE IS WRITTEN, AND THAT IS THE SUBSTANCE OF THE CHANGE.**
     * Slice A dated the trial from registration. Stripe owns the trial clock
     * now: it starts when the card is captured, `trial_ends_at` is Stripe's own
     * `trial_end` projected back onto this row, and inventing a date here would
     * put a second clock next to it — with the two disagreeing for exactly as
     * long as somebody takes to finish Checkout, which is the interval this
     * whole state exists to describe. The cost is that a person who registers
     * and never checks out has no trial end recorded, because they have no
     * trial; `29` §12.1 has no rule requiring one, and slice C decides what such
     * an account loses and when.
     *
     * ⛔ **THE LAST CLAUSE OF THAT PARAGRAPH IS NO LONGER TRUE AND THE REST OF IT
     * IS — BOTH READINGS KEPT AND DATED, 2026-08-25** (9328, 4368's rule). *"They
     * have no trial"* was the settled reading for a year and the owner has ruled
     * the other way: **a `pending_checkout` account is on a free trial and it is
     * bounded at `billing.trial_days` from registration.** What is unchanged is
     * this write — **still no date, still no second stored clock** — because the
     * bound is a read-time question over `businesses.created_at` rather than a
     * column. {@see self::noCardTrialEndsAt()} carries the whole argument for why
     * it could not be a column even if somebody wanted one. So the hazard this
     * paragraph refuses is still refused: there is exactly one stored trial date
     * on this row and the gateway still owns it.
     *
     * IDEMPOTENT BY THE UNIQUE KEY, not by a check-then-insert. `business_id` is
     * unique on this table, so a second provisioning attempt for the same
     * business gets the existing row rather than a duplicate or a constraint
     * violation surfacing out of a registration transaction.
     */
    public function openPendingSignup(Business $business): Subscription
    {
        $existing = $this->for($business);

        if ($existing instanceof Subscription) {
            return $existing;
        }

        $subscription = new Subscription;

        // forceFill(), because $guarded holds business_id and this is the one
        // class allowed past it. BelongsToTenant fills business_id from the
        // established tenant on save, which provisioning has set by the time it
        // calls here — the same ordering every other provisioned row relies on.
        $subscription->forceFill([
            'plan' => Plan::Base,
            'status' => SubscriptionStatus::PendingCheckout,
        ])->save();

        return $subscription;
    }

    /**
     * The Stripe customer id for this business, if one has been created.
     *
     * Read from the tenant-owned row, which is the authority — {@see
     * StripeCustomer} is an index of it for the one caller that has no tenant.
     */
    public function stripeCustomerFor(Business $business): ?string
    {
        return $this->for($business)?->stripe_customer_id;
    }

    /**
     * Record the Stripe customer, in both places, or in neither.
     *
     * ⚠️ **THE TRANSACTION IS THE WHOLE POINT AND IT WENT IN ON THE WAY IN.**
     * Decision 518 is two slices old and identical in shape: a docblock claiming
     * that two writes "move together" above two sequential statements. Here the
     * halves fail in opposite, equally bad directions — a tenant row with no
     * index means every future webhook for this customer resolves to nothing and
     * is filed `unlinked`, and an index with no tenant row means a business
     * whose next Checkout attempt creates a *second* Stripe customer with the
     * first one's subscription attached to it.
     */
    public function linkStripeCustomer(Business $business, string $customerId): void
    {
        DB::transaction(function () use ($business, $customerId): void {
            $subscription = $this->for($business) ?? $this->openPendingSignup($business);

            $subscription->forceFill(['stripe_customer_id' => $customerId])->save();

            // The platform-scoped index (decision 682). updateOrCreate rather
            // than create: a business retrying Checkout after a lost response
            // arrives here with the customer it already has, and the row is
            // keyed on the vendor id in any case.
            StripeCustomer::query()->updateOrCreate(
                ['stripe_customer_id' => $customerId],
                ['business_id' => $business->id, 'created_at' => Carbon::now()],
            );
        });
    }

    /**
     * Record which of the two prices a Checkout Session was built from, and what
     * that price actually was.
     *
     * ⚠️ **THE ONE THING ON THIS ROW STRIPE CANNOT TELL US** (decision 2680).
     * Every price is inline `price_data` (689), so a `customer.subscription.*`
     * event carries an amount and an interval and no name for the term behind
     * them — and "$997 every year" and "the annual plan" are the same fact only
     * until somebody moves the price. This is `authorize_net_starts_on`'s move
     * (2138) on the other gateway: store what we sent.
     *
     * ⚠️ **IT TAKES A `PlanSelection` RATHER THAN A `BillingTerm`, AND THE
     * WIDENING IS THE POINT (3443, 3444).** The term alone stopped being enough to
     * describe what we quoted the moment an existing customer's price had to
     * outlive a registry edit: "the annual plan" names a row in the registry,
     * which moves, and this row has to remember the number. Adding a second method
     * beside this one would have been the cheaper edit and the worse design —
     * **two calls at one call site is one call somebody forgets**, which is
     * `CLAUDE.md`'s writerless-column shape arriving through the door marked
     * "additive". Widening the parameter means the compiler asks.
     *
     * ⛔ **IT WRITES NO STATUS, NO ID AND NO PERIOD, WHICH IS WHY IT IS STILL NOT
     * A SECOND SOURCE OF TRUTH.** Webhooks stay the authority for everything that
     * says whether a subscription exists (2056); this says what was on the page
     * when somebody pressed the button, which no webhook will ever carry. **A
     * price agreed is not a price charged** — the gateway holds the charge and
     * nothing here touches it.
     *
     * ⛔ **AND IT TAKES A {@see PlanQuote} RATHER THAN A `PlanSelection` SINCE
     * 4346 — THE WIDENING IS THE POINT A SECOND TIME.** Handing over the
     * selection meant this class priced it again, from *its own* `PlanCharges`
     * with its own memo of the live offers, after the caller had already priced
     * and sent one. Two instances, one purchase, a window closing in between, and
     * a row storing a price the gateway is not charging — 3444 through the door
     * marked "the writer reads the same registry".
     */
    public function recordQuotedSelection(Business $business, PlanQuote $quote): void
    {
        DB::transaction(function () use ($business, $quote): void {
            $subscription = $this->for($business) ?? $this->openPendingSignup($business);

            $subscription->forceFill([
                'term' => $quote->selection->term,
                ...$this->agreedPriceColumns($quote),
            ])->save();
        });
    }

    /**
     * Record that an operator has attached extra locations to a live plan.
     *
     * ⛔ **T176 P25's "OPERATOR ATTACHES THE SKU PER SCHEDULE", AND IT IS A
     * RECORD OF WHAT WAS ARRANGED RATHER THAN AN ARRANGEMENT.** Nothing here
     * calls a gateway: the operator changes the recurring amount at the vendor,
     * which is where the subscription lives, and this writes down what our side
     * was told. `recordQuotedSelection()` and `recordCancellationRequest()` are
     * the same shape a few columns over — **no status, no vendor id, no period**,
     * so 2056's webhooks-are-the-source-of-truth rule is untouched.
     *
     * ⛔ **IT MOVES THE COUNT AND NEVER THE RATE, WHICH IS THE WHOLE OF 3443 ON
     * THIS PATH.** A tenant who bought at one add-on price and adds a location
     * two years later is owed the rate they agreed to, and that rate is already
     * on the row in `additional_location_cents`. Re-reading a price here — the
     * obvious edit — would silently reprice them at the moment somebody was doing
     * them a favour. ⚠️ **The two writers therefore differ on purpose**: at signup
     * there is no agreement yet, so the price of the day is the only answer;
     * afterwards there is one, and the price of the day is the wrong answer.
     * ⚠️ **"THE REGISTRY" IS NO LONGER THE RIGHT NAME FOR EITHER HALF OF THAT
     * (4346).** This paragraph said `agreedPriceColumns()` re-read the registry at
     * signup; since 4346 it reads nothing at all — the caller resolves a
     * {@see PlanQuote} before the vendor is called and hands the figures over —
     * and the price of the day may come from a live offer rather than from
     * `plan_entitlements`. The *argument* is unchanged; only the source it names
     * was.
     *
     * ⛔ **A ROW WITH NO STORED RATE IS REFUSED RATHER THAN GIVEN TODAY'S.** A
     * subscription written before the agreed-price columns existed has all four
     * null (see the migration), so attaching a count to it would produce a row
     * saying "three extra locations at no stated rate" — which
     * {@see PlanCharges::agreedPriceFor()} then reads through its fallback as
     * **zero extra locations**, quoting a total nobody is charged. Refusing sends
     * the operator to the one question that has to be answered by a person: what
     * did this customer actually agree to.
     *
     * @param  int  $additionalLocations  The **total** beyond the included one,
     *                                    not an increment. A delta would make a
     *                                    retried operator action add twice, and
     *                                    the screen already shows the total.
     *
     * @throws RuntimeException There is no subscription, or it carries no agreed
     *                          price to attach a count to.
     */
    public function recordAdditionalLocations(Business $business, int $additionalLocations): void
    {
        if ($additionalLocations < 0) {
            throw new RuntimeException(
                'A subscription cannot carry a negative number of additional locations.'
            );
        }

        DB::transaction(function () use ($business, $additionalLocations): void {
            $subscription = $this->for($business);

            if (! $subscription instanceof Subscription) {
                throw new RuntimeException(
                    'This business has no subscription, so there is nothing to attach '
                    .'locations to. Sell them a plan first.'
                );
            }

            if ($subscription->additional_location_cents === null) {
                throw new RuntimeException(
                    'This subscription predates the stored price, so we do not know what '
                    .'rate this customer agreed to for an extra location. Find out what '
                    .'they were sold before attaching one.'
                );
            }

            /*
             * ⛔ A COUNT MAY NOT BE SET BELOW WHAT THE TENANT ALREADY HOLDS, AND
             * THIS GUARD EXISTS BECAUSE THE PATH THAT REACHES IT WAS BUILT IN THE
             * SAME SLICE.
             *
             * The count is *replaced* rather than incremented, so an operator
             * typing 0 for a tenant holding three locations would leave them
             * entitled to one while holding three — the other two still live,
             * still publishing feedback pages, still texting, and billed for
             * nothing. Nothing anywhere would notice: every screen would render
             * a coherent, wrong number.
             *
             * ⚠️ IT IS UNREACHABLE FROM A CHECKOUT and reachable from the
             * operator screen, which is why it lives here rather than there. Both
             * checkouts refuse once a gateway subscription exists, so a business
             * reaches one at most once in its life holding exactly the one
             * location `TenantProvisioner` made it; `Admin\TenantLocations` can
             * be opened any number of times against a tenant with any number of
             * locations.
             *
             * ⚠️ AND IT COUNTS ROWS RATHER THAN ASKING `LocationAllowance`, which
             * would be the tidier call and a circular one — that class reads this
             * one. The count is tenant-scoped by the global scope on `Location`
             * like every other read here.
             */
            $held = Location::query()->where('business_id', $business->id)->count();
            $permitted = LocationAllowance::INCLUDED + $additionalLocations;

            if ($permitted < $held) {
                throw new RuntimeException(
                    "This business already has {$held} locations set up, so the plan cannot "
                    ."be recorded as covering {$permitted}. Remove the locations they are "
                    .'giving up first, or record a total that covers what they have.'
                );
            }

            $subscription->forceFill([
                'additional_locations' => $additionalLocations,
            ])->save();
        });
    }

    /**
     * Which business a Stripe customer belongs to, asked with no tenant set.
     *
     * ⚠️ **THE ONE READ IN THIS CLASS THAT DELIBERATELY DOES NOT GO THROUGH THE
     * TENANT BOUNDARY**, because it is what establishes the tenant. Everything
     * else here is scoped by the global scope on `Subscription`; this reads the
     * un-scoped index instead, and its caller's very next line is
     * `Tenancy::actingAs()`. See 682 for why the index exists at all.
     */
    public function businessIdForStripeCustomer(string $customerId): ?int
    {
        return StripeCustomer::query()->find($customerId)?->business_id;
    }

    /**
     * Project a verified Stripe subscription onto this business's row.
     *
     * Returns false when the event is older than the state already stored, so
     * that the caller can file it as `GatewayEventOutcome::Superseded`
     * rather than silently doing nothing.
     *
     * ⚠️ **RECONCILED AGAINST THE OBJECT, NEVER APPLIED AS A TRANSITION.**
     * Stripe's own guidance is that events arrive out of order and that a
     * handler must not depend on receiving them in sequence. Every event carries
     * the subscription as a complete snapshot, so this overwrites rather than
     * stepping — and the watermark is what stops an overwrite going backwards.
     * The damaging reordering is specific and worth naming: an `updated` from
     * before a cancellation, arriving after it, would otherwise reinstate a
     * subscription that has ended.
     */
    public function applyStripeSubscription(Business $business, StripeSubscriptionState $state): bool
    {
        return DB::transaction(function () use ($business, $state): bool {
            $subscription = $this->for($business) ?? $this->openPendingSignup($business);

            $syncedAt = $subscription->stripe_synced_at;

            if ($syncedAt !== null && $state->observedAt->lessThan($syncedAt)) {
                return false;
            }

            $subscription->forceFill([
                'status' => $state->status,
                'stripe_customer_id' => $state->customerId,
                'gateway' => 'stripe',
                'stripe_subscription_id' => $state->subscriptionId,
                'trial_ends_at' => $state->trialEndsAt,
                'current_period_end' => $state->currentPeriodEnd,
                'ends_at' => $state->endsAt,
                'stripe_synced_at' => $state->observedAt,
            ])->save();

            // The index again, for the case this slice cannot rule out: a
            // subscription event arriving for a customer whose create response
            // was lost, resolved through its own `metadata.business_id`. Without
            // this, that business's *next* event would be unlinked all over
            // again.
            StripeCustomer::query()->updateOrCreate(
                ['stripe_customer_id' => $state->customerId],
                ['business_id' => $business->id, 'created_at' => Carbon::now()],
            );

            return true;
        });
    }

    /**
     * The business's subscription, if it has one.
     *
     * Tenant-scoped by the global scope on the model like everything else — the
     * $business argument names the intent rather than doing the filtering, and a
     * mismatch between it and the established tenant returns null rather than
     * another tenant's row.
     */
    public function for(Business $business): ?Subscription
    {
        return Subscription::query()
            ->where('business_id', $business->id)
            ->first();
    }

    /**
     * Whether this business may use the product.
     *
     * ⚠️ **NO SUBSCRIPTION MEANS ENTITLED, AND THAT IS FAIL-OPEN ON PURPOSE.**
     * Every business provisioned before this slice existed has no row, and
     * `29` §2 rule 43's "never hard-fail" plus `CLAUDE.md`'s "never bill by
     * surprise" both point the same way: the failure mode of guessing wrong here
     * is locking a paying customer out of a product they are paying for, on the
     * strength of a row that was never written. Slice B, which gives every
     * tenant a row at signup, is where refusing an absent row becomes safe — and
     * it is still the owner's call, because "what a delinquent tenant loses" is
     * explicitly undecided.
     *
     * ⛔ **AND IT READ `status` AND NOTHING ELSE UNTIL 2026-08-23, WHICH IS WHY A
     * CANCELLED ANNUAL PLAN ENTITLED FOR EVER** (8960, 8961). The paid-term arm
     * in {@see self::applyAuthorizeNetSubscription()} holds a cancelled annual row
     * at `active` with a future `ends_at` so the tenant keeps the year they bought
     * (2748) — a ruling this method does not touch and must not — and **nothing
     * revisited the row afterwards.** The vendor sends no further notification for
     * a subscription it has already cancelled, no scheduled command reads
     * `ends_at` against a clock, and no writer moves the status again. So the day
     * the year ran out did nothing: the monthly allotment kept being minted, and
     * `AutoTopUps` kept charging a stored card — spelled bare
     * rather than with an `@see` tag, because Pint promotes one into a real `use`
     * import and this class deliberately imports no sibling service.
     *
     * ⚠️ **THE FIX IS A READ-TIME QUESTION AND NOT A SWEEP, DELIBERATELY** (8962).
     * The arm above already asks *"is the term still in the future?"* at webhook
     * time; this asks the same question at read time, so it is true the instant
     * the clock passes and needs nothing to have run. A nightly command writing
     * `canceled` would have been a containment whose failure mode is silence —
     * `warehouse:replay`'s shape, and `sending_health_windows`' before it.
     *
     * ⚠️ **{@see Subscription::accessHasEnded()} IS ASKED BEFORE THE STATUS AND
     * BOTH ORDERS GIVE THE SAME ANSWER**, because every unentitled status already
     * refuses. It is first so that the arm carrying the new rule is the one a
     * reader meets, and so that a null status keeps 588's fail-open on the only
     * path where a null can occur.
     *
     * ⚠️ **AND {@see self::noCardTrialHasEnded()} SITS BETWEEN THE TWO, WHERE THE
     * ORDER IS IMMATERIAL FOR A CHECKED REASON RATHER THAN AN ASSUMED ONE** (9328).
     * All three writers of `ends_at` in this class write a status alongside it and
     * none of them writes `pending_checkout` — the ARB projection, the Stripe
     * projection and `recordAuthorizeNetCancellation()` — so **a
     * `pending_checkout` row never carries an `ends_at` at all** and the two arms
     * cannot both be reachable on one row. It is second so that the arm reading a
     * date we **recorded** is met before the arm computing one.
     *
     * ## Every arm that never ends, and who ruled it — recorded 2026-08-24
     *
     * ⛔ **THE 2026-08-23 FIX CLOSED ONE ARM, NOT THE SHAPE, AND READING IT AS
     * "ONE ARM LEFT" SIZES THE WORK WRONG.** `accessHasEnded()` covers **only**
     * rows that already carry a non-null `ends_at`; it writes nothing and
     * schedules nothing. Five arms of this method still end at nothing on their
     * own, and **every one of them is a ruling rather than an oversight** — which
     * is precisely why they are written down together, because a ruled fail-open
     * and an unnoticed one look identical from a call site:
     *
     *   - **no row at all** — 588, fail-open, argued at the top of this
     *     docblock. `Console\Commands\ResetMonthlyCredits` refuses it
     *     independently, so the one path that mints billable SMS fails *closed*
     *     on it; every other consumer inherits the fail-open.
     *   - **a null `status`** — the `?? true` below, and it is **588's own
     *     population reached by a second route rather than a sixth policy**.
     *     Enumerated 2026-08-24: no writer in `app/` can produce it. Both
     *     column writers below take a non-nullable `SubscriptionStatus`,
     *     `openPendingSignup()` always writes `pending_checkout`, and the
     *     `subscriptions.status` column is nullable only because
     *     `2026_08_04_213116_add_trial_dates_to_subscriptions_table` says in
     *     its own CHECK that *"`status IS NULL` is permitted for the rows that
     *     predate this slice"*. **So the arm is reachable only by a row older
     *     than any writer** — the same cohort as no row at all.
     *   - ⛔ **`pending_checkout` — CLOSED 2026-08-25 AND THE ONLY ONE OF THE
     *     FIVE THAT HAS MOVED** (9328). It read: *"588 again, argued case by case
     *     in `Enums\SubscriptionStatus`. A CHECK forbids a vendor subscription id
     *     on this status, so `ends_at` is never written for it and an abandoned
     *     Checkout is entitled indefinitely, on purpose."* Every clause of that is
     *     still a true statement about the **row**; what changed is the ruling.
     *     The owner bounded it at `billing.trial_days` from registration, and
     *     {@see self::noCardTrialHasEnded()} is the arm — **above** the status
     *     read, because the enum cannot see a clock and must not be taught to.
     *     ⚠️ **`SubscriptionStatus::PendingCheckout::isEntitled()` therefore still
     *     answers `true` and that is not an oversight**: it answers *"does this
     *     state entitle"*, which is a question about a word, and the bound is a
     *     question about a date. Reading the enum alone is now strictly weaker
     *     than reading this method — which is why `ResetMonthlyCredits`, the one
     *     caller that deliberately reads the enum, asks the clock separately.
     *   - **`past_due`** — deliberate, argued at `SubscriptionStatus::isEntitled()`:
     *     this refuses to cut an account off during a gateway's retry window and
     *     leaves *"what a delinquent tenant loses and when"* to the owner.
     *   - **`active` with a null `ends_at` and a matured `annual_term_ends_on`**
     *     — **decision 2749, and the one arm that is genuinely open with the
     *     owner.** `accessHasEnded()` deliberately does not read
     *     `annual_term_ends_on`; see {@see Subscription::accessHasEnded()} for
     *     why `ends_at` is what tells the two populations apart.
     *
     * ⛔ **DO NOT CLOSE ANY OF THE REMAINING FOUR FROM HERE.** Who is entitled to
     * a paid product is the owner's ruling; `CLAUDE.md`'s ambiguity tiebreakers decide
     * support surface, stored PII and marginal cost, and none of the three
     * reaches this question. **`trialing` is not in the list**: a trialing row
     * has a live subscription behind it at one of the two gateways by CHECK, so
     * what ends it is the same webhook that ends `active`, and it is not an arm
     * that ends at nothing.
     */
    public function isEntitled(Business $business): bool
    {
        $subscription = $this->for($business);

        if (! $subscription instanceof Subscription) {
            return true;
        }

        if ($subscription->accessHasEnded()) {
            return false;
        }

        if ($this->noCardTrialHasEnded($business, $subscription)) {
            return false;
        }

        return $subscription->status?->isEntitled() ?? true;
    }

    /**
     * When the no-card free trial this business registered for runs out.
     *
     * ⛔ **THIS IS A NEW CLOCK AND NOT A READ OF ONE THAT EXISTED** (9328). Until
     * 2026-08-25 there was no trial clock in this schema for an owner who never
     * added a card: `trial_ends_at` is written in exactly two places and **both
     * require a card** — Stripe's own `trial_end` and the Authorize.Net start
     * date — and `openPendingSignup()` deliberately wrote none, saying so at the
     * write. So the only representation of the no-card trial was
     * `SubscriptionStatus::PendingCheckout`, which is indefinite.
     *
     * ⛔ **IT IS DERIVED RATHER THAN STORED, AND THE REASON IS A PROPERTY OF THIS
     * SCHEMA RATHER THAN A PREFERENCE** (9329). A stored `trial_ends_at` would
     * need a backfill for every row that predates it, and `subscriptions` is
     * `ENABLE` + `FORCE ROW LEVEL SECURITY` on `app.business_id` — a migration
     * establishes no tenant, so `UPDATE subscriptions SET trial_ends_at = …`
     * matches **zero rows and reports success**, which is the failure
     * `2026_08_14_113356_store_message_cost_entries_in_millicents` records in
     * full. The DDL escape it used (a generated column, then `DROP EXPRESSION`)
     * cannot reach another table, and the registration moment lives on
     * `businesses`. The remaining option is a sweep command, which is what 8962
     * refused for `accessHasEnded()`: *"a nightly command writing `canceled`
     * would have been a containment whose failure mode is silence."*
     *
     * ⚠️ **SO IT IS THE SAME SHAPE AS {@see Subscription::accessHasEnded()}** — a
     * read-time question that is true the instant the clock passes and needs
     * nothing to have run — and it inherits that arm's whole argument.
     *
     * ⚠️ **THE ANCHOR IS `businesses.created_at` AND NOT THE SUBSCRIPTION ROW's**,
     * though the two are written in one transaction by `CreateNewUser`. This
     * method is called from {@see self::recordQuotedSelection()}'s cohort as well:
     * that method does `for() ?? openPendingSignup()`, so a business old enough to
     * have no row at all would be handed a **fresh** row today and, with the row's
     * own timestamp as the anchor, a fresh fourteen days with it. The owner's
     * ruling is *"fourteen days from registration"*, and registration is when the
     * tenant was created.
     *
     * ⚠️ **NULL MEANS THERE IS NO CLOCK, AND THAT ARM HAS NO KNOWN OCCUPANT.**
     * `businesses.created_at` is nullable only because `$table->timestamps()`
     * makes it so; every writer sets it. An account whose registration moment we
     * cannot read is one this rule has no standing to bound, and the fail-open
     * matches 588's for the same population.
     *
     * ⚠️ ONE EXCEPTION, WRITTEN ONLY FORWARD (wave 825): `no_card_trial_extended_until` is a per-row override staff set from the Ops tenant screen. It needs no backfill (null = no extension), so 9329's argument does not reach it, and it can only extend.
     *
     * ⚠️ **THE LENGTH IS ASKED OF {@see PlanCharges::trialDays()} AND NOT OF THE
     * REGISTRY**, so that method stays what its own docblock claims — the only
     * reader of `billing.trial_days` in the billing services. It **throws** on a
     * non-positive value rather than inventing one, and that refusal is inherited
     * here deliberately: a trial length invented at a call site is a price term
     * invented at a call site. The status is tested *first*, so the read is only
     * ever reached for an account nobody has paid for.
     *
     * ⛔ **THE ROW IS A REQUIRED PARAMETER AND NOT AN OPTIONAL ONE.** Every
     * caller already holds it — `isEntitled()` has just read it, the monthly
     * sweep needs it for the plan, both screens render other fields off it — and
     * an optional argument here would mean *"null: look it up"* and *"null: there
     * is no row"* in one signature, which are opposite answers. It also keeps the
     * whole rule at one site: the four callers would otherwise each carry their
     * own `status === PendingCheckout ? … : null`, which is four copies of the
     * clause that decides who is on a trial.
     *
     * ⚠️ **NULL MEANS "NOT ON THE NO-CARD TRIAL", AND THAT IS THREE DIFFERENT
     * ACCOUNTS**: one past Checkout, one with no subscription row at all, and one
     * whose registration moment we cannot read. None of the three is on this
     * clock and no screen may invent a date for any of them.
     */
    public function noCardTrialEndsAt(Business $business, ?Subscription $subscription): ?Carbon
    {
        if (! $subscription instanceof Subscription
            || $subscription->status !== SubscriptionStatus::PendingCheckout) {
            return null;
        }

        $registeredAt = $business->created_at;

        if (! $registeredAt instanceof Carbon) {
            return null;
        }

        $ends = $registeredAt->copy()->addDays($this->charges->trialDays());
        $extended = $subscription->no_card_trial_extended_until;

        // A per-account extension set by platform staff (wave 825) — it can only
        // lengthen the trial, never shorten it, and null means "no extension".
        return $extended instanceof Carbon && $extended->gt($ends) ? $extended : $ends;
    }

    /**
     * Whether this business is on the no-card free trial and it has run out.
     *
     * ⛔ **THE OWNER'S RULING OF 2026-08-25, AND IT IS NARROW ON PURPOSE** (9328):
     * *"a `pending_checkout` trial is bounded at fourteen days from
     * registration."* It says nothing about a missing row (588's fail-open, kept),
     * nothing about `past_due` (still the owner's), and nothing about the matured
     * annual term of 2749. **Only `pending_checkout` is bounded here.**
     *
     * ⚠️ **IT IS {@see self::noCardTrialEndsAt()} WITH THE CLOCK COMPARED, AND
     * DELIBERATELY NOTHING ELSE.** A second copy of *"which status is on a
     * trial"* here is the drift 3441 names in as many words for its own rule.
     *
     * ⛔ **THE ROW IS A PARAMETER RATHER THAN A SECOND READ, AND THAT IS WHAT
     * MAKES THE TWO DOORS ASK ONE QUESTION.** `ResetMonthlyCredits` reads the
     * subscription row itself — it needs the plan anyway, and a missing row must
     * fail *closed* there where {@see self::isEntitled()} fails open (8961) — so
     * handing the row in is what lets that command consult this rule without a
     * second query and without a second copy of it. 8961 is the standing lesson:
     * fixing the service alone left the one path that mints 500 real, billable
     * SMS every month untouched.
     *
     * ⚠️ **A NULL ROW IS NOT ON A TRIAL.** It is 588's cohort — a business
     * provisioned before this table had a writer — and this method has no more
     * standing over it than {@see self::isEntitled()} does.
     *
     * ⚠️ **NOTHING HERE IS AN EXPIRY OF ANYTHING.** No balance moves, no row is
     * written, and finishing Checkout makes every one of these answers change
     * back. The wording on every surface that renders this has to say so — see
     * `resources/views/livewire/account/credit.blade.php`.
     */
    public function noCardTrialHasEnded(Business $business, ?Subscription $subscription): bool
    {
        return $this->noCardTrialEndsAt($business, $subscription)?->isPast() === true;
    }

    /**
     * Extend one account's no-card trial to `$until` (wave 825). Refuses an account
     * that is not on a no-card trial and a date that is not in the future; the
     * caller runs this inside `Tenancy::actingAs()` and writes the audit entry.
     */
    public function extendNoCardTrial(Business $business, Carbon $until): Subscription
    {
        $subscription = $this->for($business);

        if (! $subscription instanceof Subscription || $subscription->status !== SubscriptionStatus::PendingCheckout) {
            throw new RuntimeException('This account is not on a no-card trial, so there is no trial to extend.');
        }

        if (! $until->isFuture()) {
            throw new RuntimeException('The new trial end must be in the future.');
        }

        $subscription->forceFill(['no_card_trial_extended_until' => $until])->save();

        return $subscription->refresh();
    }

    /**
     * Record the Authorize.Net customer profile, in both places, or in neither.
     *
     * ⚠️ **THE SAME TRANSACTION RULE AS `linkStripeCustomer()`, AND THE HALVES
     * FAIL IN THE SAME OPPOSITE DIRECTIONS** — a tenant row with no index means
     * every future notification for this profile resolves to nothing and is
     * filed `unlinked`; an index with no tenant row means a business whose next
     * checkout attempt creates a *second* customer profile with the first one's
     * subscription attached to it.
     *
     * ⚠️ **AND THE GATEWAY IS WRITTEN HERE, WHICH IS THE NEW PART.** A row's
     * `gateway` and its vendor ids move together or the CHECK added with this
     * slice refuses the save. That is deliberate: a subscription "on Stripe"
     * carrying an ARB id is representable in no version of this schema, because
     * every reader that branches on `gateway` would then read the wrong vendor's
     * state.
     */
    public function linkAuthorizeNetCustomer(
        Business $business,
        string $customerProfileId,
        string $paymentProfileId,
    ): void {
        DB::transaction(function () use ($business, $customerProfileId, $paymentProfileId): void {
            $subscription = $this->for($business) ?? $this->openPendingSignup($business);

            $this->refuseGatewayChange($subscription, PaymentGateway::AuthorizeNet);

            $subscription->forceFill([
                'gateway' => PaymentGateway::AuthorizeNet,
                'authorize_net_customer_profile_id' => $customerProfileId,
                'authorize_net_payment_profile_id' => $paymentProfileId,
            ])->save();

            AuthorizeNetCustomer::query()->updateOrCreate(
                ['authorize_net_customer_profile_id' => $customerProfileId],
                ['business_id' => $business->id, 'created_at' => Carbon::now()],
            );
        });
    }

    /**
     * Record a newly created ARB subscription, and open the trial.
     *
     * ⚠️ **`trialing` IS DERIVED FROM A DATE HERE, NOT PROJECTED FROM THE VENDOR
     * (decision 2138), AND IT IS THE ONE STATUS AUTHORIZE.NET CANNOT TELL US.**
     * ARB expresses a trial as a whole number of occurrences of the billing
     * interval, so with decision 147's 30-day cycle the shortest trial it can
     * describe is 30 days and this product sells 14 (2065). The trial is
     * therefore a future `startDate` and the vendor never calls it a trial at
     * all — it simply has a subscription that has not charged yet. We store what
     * we sent, so the state is provable rather than inferred.
     *
     * ⚠️ **NOTHING HERE PROMOTES A ROW TO `active`.** That is the webhook's,
     * exactly as it is on Stripe: this method runs on the response to our own
     * create call, and decision 2056 keeps webhooks the source of truth on both
     * gateways. A create that succeeds and a first charge that succeeds are two
     * different facts a fortnight apart.
     *
     * ⚠️ **THE TERM AND ITS END DATE ARE WRITTEN HERE BECAUSE THIS IS THE ONLY
     * PLACE THEY ARE KNOWN** (decision 2680). The vendor is told an interval and
     * a count and can never tell us which of our two prices they came from —
     * 2138's shape one level up. `annual_term_ends_on` is what stops a fully paid
     * instalment plan reading as a cancellation when ARB reports its schedule
     * `expired` three months in.
     *
     * ⚠️ **THE PRICE IS WRITTEN HERE TOO, FOR THE SAME REASON THE TERM IS**
     * (3443, 3444). This is the moment the agreement exists — the vendor has
     * accepted the subscription and the tenant has bought at whatever the registry
     * said one second ago. Writing it anywhere later would mean re-reading a
     * figure that may already have moved.
     *
     * ⛔ **THE PRICE ARRIVES ON THE {@see PlanQuote} RATHER THAN BEING LOOKED UP
     * HERE (4346).** It is written at the moment the agreement exists, and the
     * caller resolved it before the vendor was called; re-resolving it on this
     * side would price the same purchase twice, from two memos, either side of a
     * network round trip.
     *
     * @param  ?PlanQuote  $quote  What they bought and what it was quoted at. Null
     *                             is the monthly plan at today's price, the same
     *                             default both checkout paths apply, so that "no
     *                             choice made" cannot become the annual price.
     * @param  ?int  $instalmentPayments  How many payments the annual price is
     *                                    collected in, or null for one charge.
     *                                    ⚠️ The count, never the amounts (2055).
     */
    public function startAuthorizeNetSubscription(
        Business $business,
        string $subscriptionId,
        Carbon $startsOn,
        Carbon $observedAt,
        ?PlanQuote $quote = null,
        ?int $instalmentPayments = null,
        ?Carbon $termEndsOn = null,
    ): void {
        $quote ??= $this->charges->quote(PlanSelection::monthly());

        $selection = $quote->selection;

        DB::transaction(function () use (
            $business,
            $subscriptionId,
            $startsOn,
            $observedAt,
            $quote,
            $selection,
            $instalmentPayments,
            $termEndsOn,
        ): void {
            $subscription = $this->for($business) ?? $this->openPendingSignup($business);

            $this->refuseGatewayChange($subscription, PaymentGateway::AuthorizeNet);

            $annual = $selection->term === BillingTerm::Annual;

            $subscription->forceFill([
                'gateway' => PaymentGateway::AuthorizeNet,
                'status' => $startsOn->isFuture() ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                'authorize_net_subscription_id' => $subscriptionId,
                'authorize_net_starts_on' => $startsOn,
                'trial_ends_at' => $startsOn->isFuture() ? $startsOn : null,
                'authorize_net_synced_at' => $observedAt,
                'term' => $selection->term,

                // ⚠️ NULLED RATHER THAN PASSED THROUGH ON THE MONTHLY TERM. The
                // CHECK constraints refuse an instalment count or a term end on
                // anything but `annual`, and a caller that supplied one anyway
                // would meet them as a constraint violation out of a vendor call
                // that has already succeeded — money taken, row unwritten.
                'instalment_payments' => $annual ? $instalmentPayments : null,
                'annual_term_ends_on' => $annual ? $termEndsOn : null,

                ...$this->agreedPriceColumns($quote),
            ])->save();

            // The index gains the subscription id, which is what makes a
            // `subscription.failed` notification — the one that names the
            // subscription and not the profile — resolvable to a tenant at all.
            AuthorizeNetCustomer::query()
                ->where('business_id', $business->id)
                ->update(['authorize_net_subscription_id' => $subscriptionId]);
        });
    }

    /**
     * Project a verified Authorize.Net status onto this business's row.
     *
     * Returns false when the notification is older than the state already
     * stored, so the caller can file it as `GatewayEventOutcome::Superseded`
     * rather than silently doing nothing.
     *
     * ⚠️ **THE WATERMARK IS ITS OWN COLUMN AND NOT `stripe_synced_at`.** Both
     * gateways may have touched one business over its life — 2056 keeps Stripe
     * live — and a shared watermark would let a stale event from the gateway a
     * tenant left suppress a live one from the gateway they are on.
     */
    public function applyAuthorizeNetSubscription(
        Business $business,
        string $subscriptionId,
        AuthorizeNetSubscriptionStatus $vendorStatus,
        Carbon $observedAt,
    ): bool {
        return DB::transaction(function () use ($business, $subscriptionId, $vendorStatus, $observedAt): bool {
            $subscription = $this->for($business) ?? $this->openPendingSignup($business);

            $syncedAt = $subscription->authorize_net_synced_at;

            if ($syncedAt !== null && $observedAt->lessThan($syncedAt)) {
                return false;
            }

            $status = $vendorStatus->toSubscriptionStatus();

            /*
             * ⚠️ A TRIAL THAT HAS NOT STARTED STAYS A TRIAL EVEN WHEN THE VENDOR
             * SAYS `active`, AND THIS IS THE LINE THAT KEEPS 2138 HONEST.
             *
             * ARB reports a subscription as `active` from the moment it is
             * created, including through the whole of the gap before its
             * `startDate`. Projecting that word straight onto our row would move
             * a tenant from `trialing` to `active` on day one — so the trial
             * would disappear from every screen that reads this column while the
             * card has still not been charged.
             */
            if ($status === SubscriptionStatus::Active) {
                $startsOn = $subscription->authorize_net_starts_on;

                if ($startsOn !== null && $startsOn->isFuture()) {
                    $status = SubscriptionStatus::Trialing;
                }
            }

            /*
             * ⚠️ AN INSTALMENT PLAN THAT HAS BEEN PAID IN FULL IS NOT A
             * CANCELLATION, AND THIS IS THE LINE THAT KEEPS 2680 HONEST.
             *
             * ARB says `expired` when a subscription reaches its
             * `totalOccurrences` — its word for "the schedule of payments is
             * complete". On a monthly subscription that never happens (9999
             * occurrences); on an annual plan collected in three payments it
             * happens **inside the first quarter**, while the year those payments
             * bought still has nine months to run. Projecting the vendor's word
             * straight through would cancel a tenant who has paid $997 up front,
             * three months in — the failure that takes the money and withdraws
             * the product.
             *
             * ⚠️ SCOPED TO `expired` AND TO A ROW CARRYING A FUTURE TERM END,
             * WHICH IS WHY IT CANNOT REACH ANYTHING ELSE. `canceled` and
             * `terminated` are acts and stay acts: a tenant who cancels mid-term
             * is cancelled, and a subscription the vendor terminated after our
             * dunning ran out is gone. Only this arm is "we already have the
             * money".
             */
            if ($vendorStatus === AuthorizeNetSubscriptionStatus::Expired) {
                $termEndsOn = $subscription->annual_term_ends_on;

                if ($termEndsOn !== null && $termEndsOn->isFuture()) {
                    $status = SubscriptionStatus::Active;
                }
            }

            $endsAt = $status === SubscriptionStatus::Canceled ? $observedAt : null;

            /*
             * ⚠️ A TENANT WHO CANCELS A PAID YEAR KEEPS THE YEAR, AND THIS IS
             * THE SECOND HALF OF 2748 RATHER THAN AN EXCEPTION TO IT
             * (2980–2999).
             *
             * 2748 says plainly that `canceled` and `terminated` "are acts and
             * stay acts", and that stays true for every cancellation this
             * application did not initiate — a vendor-side cancellation, a
             * merchant action, a termination after dunning ran out. All of them
             * still land as `canceled` at once.
             *
             * What this arm covers is narrower and provable: **we** called
             * `ARBCancelSubscriptionRequest` because the tenant asked, on an
             * annual term whose year is already bought. Authorize.Net has no
             * `cancel_at_period_end` — the vendor's own word is that a cancelled
             * subscription "cannot be reactivated" — so stopping the renewal
             * means ending the subscription now. Projecting that straight
             * through would withdraw nine months a tenant has paid for: 2748's
             * exact failure, arriving through the door marked "the customer
             * asked for this".
             *
             * ⛔ SCOPED TO `cancellation_requested_at` BEING SET, WHICH IS THE
             * ONLY THING THAT MAKES IT SAFE. That column is written by
             * `SubscriptionCancellation` and by nothing else, from a request a
             * person authenticated and confirmed.
             *
             * ⛔ THIS ENDED "A `canceled` NOTIFICATION WITH NO REQUEST BEHIND IT
             * CAN NEVER REACH THIS ARM" AND THAT CLAIMED MORE THAN THIS ARM CAN
             * KNOW (8967). It is a claim about a *different* class: what stops
             * an unrequested `canceled` reaching here is that nothing else
             * writes the column, not that this arm checks anything about the
             * request. `CLAUDE.md`'s outer-guard shape (398) exactly — delete
             * the check upstream and this reads as though it were still safe.
             *
             * ⚠️ AND IT IS FALSIFIABLE INSIDE ONE SUBSCRIPTION'S LIFE.
             * `SubscriptionCancellation::request()` writes the column **before**
             * the vendor call, deliberately, because the vendor's notification
             * can beat our own HTTP response back — so a cancellation whose
             * vendor call then **throws** leaves this arm armed with nothing
             * cancelled at the vendor. ⚠️ **That ordering is not a defect and
             * must not be "fixed"**: clearing the column on a failed call
             * reopens the race it was chosen to close, and the residual
             * behaviour is a tenant *keeping* a year they have paid for, which
             * is the direction 2748 already chose. Written down rather than
             * acted on (8968).
             */
            if ($vendorStatus === AuthorizeNetSubscriptionStatus::Canceled
                && $subscription->cancellation_requested_at !== null) {
                $termEndsOn = $subscription->annual_term_ends_on;

                if ($termEndsOn !== null && $termEndsOn->isFuture()) {
                    $status = SubscriptionStatus::Active;

                    // The day the paid term actually runs out, so the billing
                    // page can say when access ends rather than leaving a
                    // cancelled-but-active row with nothing to show.
                    $endsAt = $termEndsOn;
                }
            }

            $subscription->forceFill([
                'gateway' => PaymentGateway::AuthorizeNet,
                'status' => $status,
                'authorize_net_subscription_id' => $subscriptionId,
                'authorize_net_synced_at' => $observedAt,
                'ends_at' => $endsAt,
            ])->save();

            return true;
        });
    }

    /**
     * Record that this tenant asked to stop renewing (2980–2999).
     *
     * ⚠️ **IT WRITES NO STATUS, NO END DATE AND NO VENDOR ID, WHICH IS WHY IT
     * IS NOT A SECOND SOURCE OF TRUTH.** 2056 keeps webhooks the authority for
     * everything that says whether a subscription exists; this says that a
     * person pressed a button on a page we rendered, which no webhook will ever
     * carry. `recordQuotedSelection()` is the same shape a few columns over.
     *
     * ⛔ **AND WRITING THE TERMINAL STATE HERE WOULD BE THE DEFECT, NOT THE
     * SHORTCUT.** A row set to `canceled` on the strength of a button is a
     * tenant locked out of a product the gateway is still billing them for, in
     * every case where the vendor call succeeded and the notification did not
     * arrive — and worse in the case where the vendor call *failed*, which is
     * the one this application cannot rule out.
     *
     * IDEMPOTENT ON THE FIRST REQUEST. A second press keeps the first
     * timestamp: the date somebody asked is evidence, and moving it forward
     * would rewrite it. The caller is free to call the vendor again — that is
     * its decision, not this one's.
     */
    public function recordCancellationRequest(Business $business, Carbon $at): void
    {
        DB::transaction(function () use ($business, $at): void {
            $subscription = $this->for($business);

            if (! $subscription instanceof Subscription) {
                return;
            }

            if ($subscription->cancellation_requested_at !== null) {
                return;
            }

            $subscription->forceFill(['cancellation_requested_at' => $at])->save();
        });
    }

    /**
     * How long one reader's claim on the pre-renewal notice holds.
     *
     * ⚠️ **THE CLAIM COVERS TWO OPERATIONS AND NOTHING ELSE** — a queue dispatch
     * and one row write — so an hour is roughly five orders of magnitude more
     * than a healthy run needs. It is chosen long rather than short on purpose:
     * every extra minute narrows the duplicate window, and the only cost of
     * being generous is that a run which *died* mid-claim waits for the next
     * daily sweep, which it would have done anyway.
     *
     * ⛔ **AND IT MUST STAY WELL UNDER A DAY.** The sweep runs daily and the
     * window is sixteen days wide (30 → 15). A claim that outlived the interval
     * would turn one crashed run into a permanently suppressed statutory
     * notice, which is the failure {@see self::recordRenewalReminder()} refuses.
     *
     * ⚠️ **IT IS NOT THE SCHEDULER MUTEX AND MUST NOT BE TUNED AGAINST IT.**
     * `routes/console.php`'s `withoutOverlapping(360)` bounds a whole sweep
     * across every business; this bounds one row for the length of one send.
     */
    public const int RENEWAL_REMINDER_CLAIM_MINUTES = 60;

    /**
     * Take the exclusive right to send this rung of the no-card trial ladder.
     *
     * ⛔ **THIS IS THE ARBITRATION AND NOTHING ELSE IN THE SWEEP IS**, which is
     * {@see self::claimRenewalReminder()}'s lesson (6969, 7100) applied before
     * the defect rather than after it. {@see TrialReminders}
     * reads the clock, then sends, then would have written — and a `SELECT`
     * cannot stop a second reader, because whatever it answered is already
     * history by the time the caller acts on it. What arbitrates is a **write
     * that fails for the second reader**: this one UPDATE takes the row lock,
     * and under Postgres' READ COMMITTED a second concurrent UPDATE blocks on
     * it and then re-evaluates its own `WHERE` against the *committed* row,
     * which by then already names this rung.
     *
     * ⛔ **AND IT NEVER LAPSES, WHICH IS THE OPPOSITE OF THE RENEWAL CLAIM AND
     * IS DECIDED BY THE CONSEQUENCE RATHER THAN BY SYMMETRY** (9396). That
     * claim expires because a permanent one would trade *"a notice sent twice"*
     * for *"a statutory notice never sent"*. Nothing on this ladder is owed to
     * anybody: `RenewalReminders` bounds the Automatic Renewal Law to *"a year
     * or longer"* and says outright that inventing a wider duty *"would be its
     * own kind of wrong"*, and a no-card trial cannot auto-convert because there
     * is no payment instrument to convert it with. So a run that dies between
     * this claim and the queue push loses one of four warnings, and that is the
     * cheaper failure than mailing somebody the same sentence twice.
     *
     * ⛔ **THE PERMITTED PRIOR VALUES ARE DERIVED FROM THE CONFIGURED TRIAL
     * LENGTH AND NEVER FROM THE ENUM's DECLARATION ORDER** (9397). A rung may be
     * claimed when nothing has been sent, or when what was sent fires **earlier
     * in this tenant's trial** — that is, at a strictly greater number of days
     * remaining. At `billing.trial_days = 7` the day-10 rung's four-days-left
     * trigger is reached *before* the halfway rung's, so a permitted-set built
     * from `LifecycleRung::cases()` order would refuse a rung that is genuinely
     * next. {@see LifecycleRung::trialRungs()} says so at its own declaration.
     *
     * ⚠️ **AND THE SAME EXPRESSION IS WHAT REFUSES A REPEAT.** A rung's own
     * trigger is not strictly greater than itself, so it is never in its own
     * permitted set — which also closes the one way the days-remaining figure can
     * go *up* rather than down: an operator lengthening `billing.trial_days`
     * mid-trial. Without it a tenant who had already been told *"Tomorrow it
     * pauses"* could be told *"4 days left"* afterwards.
     *
     * ⚠️ **NO TRANSACTION, FOR {@see self::claimRenewalReminder()}'s REASON.** A
     * single UPDATE is already atomic, and a surrounding transaction would only
     * delay the moment the second reader can see the claim — which is the one
     * thing this method exists to publish.
     *
     * ⚠️ **RLS AND THE GLOBAL SCOPE BOTH APPLY.** A claim attempted from another
     * tenant's context matches no row and is refused as `false`, which is the
     * correct answer for a caller who cannot see the row.
     *
     * @return bool whether this caller may send the rung
     */
    public function claimTrialRung(Business $business, LifecycleRung $rung): bool
    {
        $trialDays = $this->charges->trialDays();
        $remaining = $rung->daysRemainingAtSend($trialDays);

        if ($remaining === null) {
            throw new InvalidArgumentException(sprintf(
                'The lifecycle rung `%s` is not on the trial ladder, so `subscriptions.trial_rung_sent` '
                .'has nothing to say about it. The usage ladder is a different clock — credits '
                .'consumed rather than days elapsed — and its beats have no writer here.',
                $rung->value,
            ));
        }

        $earlier = array_values(array_map(
            static fn (LifecycleRung $candidate): string => $candidate->value,
            array_filter(
                LifecycleRung::trialRungs(),
                static fn (LifecycleRung $candidate): bool => $candidate->daysRemainingAtSend($trialDays) > $remaining,
            ),
        ));

        $claimed = Subscription::query()
            ->where('business_id', $business->id)
            ->where(function ($query) use ($earlier): void {
                $query->whereNull('trial_rung_sent');

                if ($earlier !== []) {
                    $query->orWhereIn('trial_rung_sent', $earlier);
                }
            })
            ->update(['trial_rung_sent' => $rung->value]);

        return $claimed === 1;
    }

    /**
     * Give a trial rung back, having established that nothing was sent — 10992.
     *
     * ⛔ **THIS IS NOT A LAPSE AND MUST NEVER BE TURNED INTO ONE.**
     * {@see self::claimTrialRung()} never expires, and 9396's argument for that
     * is untouched: a time-based lapse trades *"a notice sent twice"* for
     * *"a rung lost"* **without knowing which happened**, and on this ladder the
     * duplicate is the worse outcome. This is the opposite shape — a
     * compensating write made in the same call, on an arm where the caller can
     * prove the transport was never contacted.
     *
     * ⛔ **WHAT MAKES THAT PROVABLE IS THAT EVERY `MailNotDeliverable` IS THROWN
     * STRICTLY BEFORE THE SEND.** All twelve of its factories are reached from
     * `PlatformMailer::assertDeliverable()`, `assertCeilingIsStated()`, the
     * headroom check, `canSpamClassOf()` or `CanSpamFooters` — every one of them
     * above `Notifications::route(...)->notifyNow(...)` in `deliverNow()`, and
     * `MailQuota::record()` is below it. ⚠️ **A `Symfony\…\TransportException`
     * is the opposite and must not reach here**: SMTP can accept a message and
     * then fail on the response, so that arm keeps 9396's trade and loses the
     * rung. {@see TrialReminders::remind()} catches the one type and no other.
     *
     * ⚠️ **CONDITIONAL ON THE ROW STILL NAMING *OUR* RUNG**, which is what stops
     * this from undoing somebody else's claim: if another reader has already
     * moved the ladder on, the `WHERE` matches nothing and this answers `false`.
     * ⚠️ **AND IT RESTORES THE PRIOR VALUE RATHER THAN NULLING THE COLUMN.** The
     * caller reads that value before it claims, so under a concurrent second
     * sweep it can in principle be stale-low — and **that cannot produce a
     * duplicate, for a reason outside this method**: {@see TrialReminders::due()}
     * is equality on a whole-day count against the clock, so it only ever offers
     * *today's* rung and never an earlier one. A stale-low restore therefore
     * widens the permitted set only for rungs nothing will ask for. ⛔ **Nulling
     * the column unconditionally would not be safe on the same reasoning** — it
     * is the same widening — **but it would also erase the record of which rung
     * a tenant last received**, which is the column's other job.
     *
     * ⚠️ **RLS AND THE GLOBAL SCOPE BOTH APPLY**, for
     * {@see self::claimRenewalReminder()}'s reason.
     *
     * @return bool whether this caller's claim was the one given back
     */
    public function releaseTrialRung(
        Business $business,
        LifecycleRung $rung,
        ?LifecycleRung $restoreTo,
    ): bool {
        $released = Subscription::query()
            ->where('business_id', $business->id)
            ->where('trial_rung_sent', $rung->value)
            ->update(['trial_rung_sent' => $restoreTo?->value]);

        return $released === 1;
    }

    /**
     * Take the exclusive right to send this renewal's pre-renewal notice.
     *
     * ⛔ **THIS IS THE ARBITRATION, AND IT IS ONE STATEMENT BECAUSE IT HAS TO
     * BE** (6969, 7100). {@see RenewalReminders::due()}
     * reads `renewal_reminded_for`, then a notice is sent, then the column is
     * written — and between the read and the write there was nothing at all.
     * Two readers inside that window both saw "nobody has sent this" and both
     * sent, and what they sent is California's statutory pre-renewal notice,
     * which cannot be taken back.
     *
     * A `SELECT` cannot arbitrate that, however it is written: whatever it
     * returns is already history by the time the caller acts on it. What
     * arbitrates is a **write that fails for the second reader**. This one
     * UPDATE takes the row lock, and under Postgres' READ COMMITTED a second
     * concurrent UPDATE blocks on that lock and then re-evaluates its own
     * `WHERE` against the *committed* row — which by then carries a fresh claim
     * and no longer qualifies. The second caller is told `false` and sends
     * nothing.
     *
     * ⚠️ **THE SHAPE IS NOT NEW HERE AND IS DELIBERATELY THE HOUSE ONE.**
     * {@see CreditPurchases::settle()} arbitrates a redelivered gateway
     * notification the same way — `update(...) === 1` with the whole idempotency
     * argument in the `WHERE` — and `routes/console.php`'s own header names it,
     * `AutoTopUps`' in-flight check and `NumberRecovery::advance()`'s row lock as
     * the second layers that make a duplicate scheduled run affordable. This
     * entry was the one it named as having none.
     *
     * ⛔ **BOTH ARMS OF THE `WHERE` ARE LOAD-BEARING AND THEY GUARD DIFFERENT
     * RACES.** The claim arm stops a *concurrent* second reader. The
     * `renewal_reminded_for` arm re-checks the record **inside the same
     * statement and under the same lock**, which is what stops a reader that
     * read `due()` an hour ago, was descheduled, and woke up after the claim it
     * would have lost had already lapsed. Dropping either one leaves a live path
     * to a duplicate notice.
     *
     * ⚠️ **A LAPSED CLAIM IS RECLAIMABLE, DELIBERATELY, AND THAT IS THE HALF
     * THAT KEEPS THIS FROM BECOMING THE OTHER FAILURE.** A permanent claim would
     * suppress the notice for ever for any run that died between claiming and
     * dispatching. {@see self::recordRenewalReminder()} ruled on that asymmetry
     * — a duplicate is an annoyance, a missing notice is a statutory failure —
     * and this method does not reverse it. What it removes is the *concurrent*
     * road to a duplicate; what survives is the one that was always there, a
     * crash between the send and the record, and after
     * {@see $this->renewalReminderClaimMinutes()} that crash produces a second
     * notice rather than none.
     *
     * ⚠️ **NO TRANSACTION HERE, UNLIKE EVERY OTHER WRITER ON THIS CLASS**, and
     * wrapping it in one would be the defect rather than the tidy-up. A single
     * UPDATE is already atomic; a surrounding transaction that also held the
     * send would keep a row lock open across a queue push, and one that held
     * only this statement would delay the moment the second reader can see the
     * claim — which is the only thing this method exists to publish.
     *
     * ⚠️ **RLS AND THE GLOBAL SCOPE BOTH APPLY.** `$business` names the intent;
     * `Subscription::query()` carries `BelongsToTenant`'s scope and the database
     * carries `tenant_isolation` on top of it, so a claim attempted from another
     * tenant's context matches no row and is refused as `false` — which is the
     * correct answer for a caller who cannot see the row.
     *
     * @return bool whether this caller may send the notice
     */
    public function claimRenewalReminder(Business $business, Carbon $renewalDate): bool
    {
        $lapsedBefore = Carbon::now()->subMinutes($this->renewalReminderClaimMinutes());

        $claimed = Subscription::query()
            ->where('business_id', $business->id)
            ->where(function ($query) use ($lapsedBefore): void {
                $query->whereNull('renewal_reminder_claimed_at')
                    ->orWhere('renewal_reminder_claimed_at', '<', $lapsedBefore);
            })
            ->where(function ($query) use ($renewalDate): void {
                $query->whereNull('renewal_reminded_for')
                    ->orWhere('renewal_reminded_for', '!=', $renewalDate->toDateString());
            })
            ->update(['renewal_reminder_claimed_at' => Carbon::now()]);

        return $claimed === 1;
    }

    /**
     * Record which renewal date the pre-renewal notice has been sent for.
     *
     * ⚠️ **THE DATE, NOT A BOOLEAN AND NOT THE SEND TIME.** California requires
     * the notice once per renewal, and the gate therefore has to be per *term*:
     * a boolean would silence next year's notice for ever, and a send timestamp
     * would need arithmetic against a moving anniversary to answer "have we
     * done this one yet". A date answers it by equality.
     *
     * ⚠️ **WRITTEN AFTER THE SEND, DELIBERATELY.** The failure modes are not
     * symmetric: writing first and failing to send leaves a tenant with no
     * notice and nothing that will ever try again, while sending first and
     * failing to write leaves a tenant with two notices. One of those is a
     * statutory failure and the other is an annoyance.
     *
     * ⚠️ **THAT RULING IS UNCHANGED AND SO IS THIS ORDER, BUT IT IS NO LONGER
     * THE ONLY THING STANDING BETWEEN A CUSTOMER AND TWO NOTICES** (7100).
     * {@see self::claimRenewalReminder()} now runs *before* the send and
     * arbitrates it, so the paragraph above describes what happens after one
     * reader has already won rather than what happens when two of them race.
     * The asymmetry is why the claim lapses instead of standing for ever: this
     * write is the confirmation, and a claim with no confirmation behind it has
     * to become sendable again or the failure mode flips to the one this
     * docblock refuses.
     */
    public function recordRenewalReminder(Business $business, Carbon $renewalDate): void
    {
        DB::transaction(function () use ($business, $renewalDate): void {
            $subscription = $this->for($business);

            if (! $subscription instanceof Subscription) {
                return;
            }

            $subscription->forceFill(['renewal_reminded_for' => $renewalDate])->save();
        });
    }

    /**
     * A declined recurring charge, recorded before dunning opens.
     *
     * ⚠️ **SEPARATE FROM `applyAuthorizeNetSubscription()` BECAUSE THE
     * NOTIFICATION IS.** `net.authorize.customer.subscription.failed` reports a
     * *payment*, and its payload does not reliably carry the subscription's
     * status — so reading a status out of it would mean inventing one. What is
     * certain from the event itself is that a charge failed, and `past_due` is
     * exactly that. The vendor's own `suspended` notification usually follows
     * and re-states it through the ordinary path.
     */
    public function markAuthorizeNetPastDue(Business $business, Carbon $observedAt): void
    {
        DB::transaction(function () use ($business, $observedAt): void {
            $subscription = $this->for($business);

            if (! $subscription instanceof Subscription) {
                return;
            }

            $syncedAt = $subscription->authorize_net_synced_at;

            if ($syncedAt !== null && $observedAt->lessThan($syncedAt)) {
                return;
            }

            $subscription->forceFill([
                'status' => SubscriptionStatus::PastDue,
                'authorize_net_synced_at' => $observedAt,
            ])->save();
        });
    }

    /**
     * Withdraw entitlement after the dunning schedule is exhausted.
     *
     * ⚠️ **THE ONE PLACE IN THIS APPLICATION THAT TAKES THE PRODUCT AWAY FOR
     * NON-PAYMENT, AND IT IS DELIBERATELY NARROW (decision 2142).**
     * `CLAUDE.md` says plainly that "nobody has decided what a delinquent tenant
     * loses or when", and `SubscriptionStatus::isEntitled()`'s docblock reserves
     * that question for the owner. What is *not* undecided is the terminal case:
     * Authorize.Net **terminates** a suspended subscription if nobody acts, so
     * at the end of the schedule there is no subscription left to be delinquent
     * on — the tenant is not being cut off early, they have stopped having a
     * subscription. **This method is only reachable from
     * `DunningOutcome::Exhausted`**, and every softer question — a grace period,
     * a read-only mode, a warning banner — is still the owner's.
     */
    public function suspendForNonPayment(Business $business, Carbon $at): void
    {
        DB::transaction(function () use ($business, $at): void {
            $subscription = $this->for($business);

            if (! $subscription instanceof Subscription) {
                return;
            }

            $subscription->forceFill([
                'status' => SubscriptionStatus::Canceled,
                'ends_at' => $at,
                'authorize_net_synced_at' => $at,
            ])->save();
        });
    }

    /**
     * Which business an ARB subscription belongs to, asked with no tenant set.
     *
     * ⚠️ **THE SECOND READ IN THIS CLASS THAT DELIBERATELY DOES NOT GO THROUGH
     * THE TENANT BOUNDARY**, because it is what establishes the tenant — the
     * same shape as `businessIdForStripeCustomer()`, and its caller's very next
     * line is `Tenancy::actingAs()`.
     *
     * ⚠️ **IT KEYS ON THE SUBSCRIPTION, NOT THE PROFILE, AND THAT IS WHY THE
     * INDEX CARRIES BOTH.** Authorize.Net's subscription notifications identify
     * the subscription; several of them do not carry the customer profile at
     * all, so a profile-only index would lose exactly the events that matter
     * most.
     */
    public function businessIdForAuthorizeNetSubscription(string $subscriptionId): ?int
    {
        return AuthorizeNetCustomer::query()
            ->where('authorize_net_subscription_id', $subscriptionId)
            ->value('business_id');
    }

    /**
     * Which business an Authorize.Net customer profile belongs to.
     */
    public function businessIdForAuthorizeNetProfile(string $customerProfileId): ?int
    {
        return AuthorizeNetCustomer::query()->find($customerProfileId)?->business_id;
    }

    /**
     * The four agreed-price columns, taken from the quote the caller sent.
     *
     * ⛔ **THE ONLY PLACE THEY ARE BUILT, AND THAT IS WHAT MAKES 3443 HOLD.** Both
     * checkout paths reach it, through the two methods above, so there is no way
     * to create a subscription in this application without recording what it was
     * sold for. `CLAUDE.md`'s writerless-column shape has eighteen instances and
     * the money version of it is the worst: a half-written group renders a
     * plausible zero on a page about somebody's bill, which is not an error
     * anybody sees.
     *
     * ⚠️ **ALL FOUR OR NONE, WHICH IS ALSO A DATABASE CHECK.** They are returned
     * as one array rather than spread across the call sites so that a future edit
     * cannot add a fifth column here and forget one of the two writers — and so
     * that `subscriptions_agreed_price_is_whole` has nothing to catch.
     *
     * ⚠️ **THE UNIT RATE, NEVER THE TOTAL.** The total is the plan price plus the
     * locations, and storing the total would lose the two rates 3443 also
     * grandfathers. What goes on the row is the two rates and the count, so the
     * total is reconstructable and the add-on rate survives for the tenant who
     * adds a location next year.
     *
     * ⛔ **IT NO LONGER ASKS A PRICE OF ANYTHING (4346).** It used to call
     * `PlanCharges` itself, which meant this class held the *second* instance
     * pricing one purchase — see {@see PlanQuote}. The numbers now arrive already
     * resolved from whoever quoted them, so there is no second lookup to disagree
     * with the first.
     *
     * @return array{
     *     price_cents: int,
     *     additional_location_cents: int,
     *     additional_locations: int,
     *     price_currency: string,
     * }
     */
    private function agreedPriceColumns(PlanQuote $quote): array
    {
        return [
            'price_cents' => $quote->unitPrice->minorUnits,
            'additional_location_cents' => $quote->additionalLocationPrice->minorUnits,
            'additional_locations' => $quote->selection->additionalLocations,
            'price_currency' => $quote->unitPrice->currency,
        ];
    }

    /**
     * ⚠️ A SUBSCRIPTION NEVER CHANGES GATEWAY IN PLACE.
     *
     * Neither vendor can be told about the other's subscription, so "moving" a
     * tenant means cancelling one and creating the other, with a gap in which
     * nothing bills them. Nothing in this application does that, and this refusal
     * is what stops the ability being acquired by accident — a `forceFill()` that
     * looks like it is only setting an id would otherwise leave one row naming
     * two live subscriptions, with both vendors charging.
     */
    private function refuseGatewayChange(Subscription $subscription, PaymentGateway $gateway): void
    {
        $current = $subscription->gateway;

        if ($current === null || $current === $gateway) {
            return;
        }

        $hasLiveSubscription = $subscription->stripe_subscription_id !== null
            || $subscription->authorize_net_subscription_id !== null;

        if ($hasLiveSubscription) {
            throw new RuntimeException(
                "This business already has a live {$current->label()} subscription. Moving it "
                ."to {$gateway->label()} means cancelling one and creating the other, and "
                .'writing both ids onto one row would leave two vendors charging the same '
                .'tenant.'
            );
        }

        // No live subscription behind it — a customer profile created and never
        // used. Switching is safe and is the ordinary case for somebody who
        // abandoned one checkout and came back to the other.
    }

    /*
     * `trialDays()` LIVED HERE AND HAS MOVED TO BillingCheckout, WITH THE TRIAL.
     *
     * Slice A read `billing.trial_days` at this write because this write was
     * what started the trial. It is not any more: Stripe starts the trial when
     * the card is captured, so the only place the number is needed is the
     * Checkout Session that tells Stripe how long it runs. Leaving a copy here
     * would be two readers of one key that can disagree about when somebody's
     * fourteen days began — decision 505's reasoning, on the key whose value the
     * marketing home also prints (518).
     */

    public function renewalReminderClaimMinutes(): int
    {
        return ($this->defaults ?? app(DefaultsRegistry::class))->int('billing.renewal.claim_minutes');
    }
}

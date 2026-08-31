<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CreditProduct;
use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Exceptions\TrialGrantRefused;
use App\Exceptions\WithheldRegistryValue;
use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\CreditLedger;
use App\Services\Billing\Subscriptions;
use App\Services\Billing\TrialEligibility;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Expire last period's allotments and grant this period's — **all three of them**
 * (decisions 3307, 3419).
 *
 * ⛔ **THIS IS THE WRITER `CreditKind::Grant` SPENT ITS WHOLE LIFE WITHOUT.** 3103
 * recorded that the case was constructed nowhere in `app/`, `BillingTest`'s
 * funding lint failed the build on anything that constructed it, and both were
 * correct while the allotments' sizes were unset — a ledger row is append-only, so
 * a guessed figure would have become the record (502's withheld-value posture
 * applied to an enum case). **3298, 3412 and 9180 set every figure**: 500 SMS,
 * 1,000 emails and $50 of AI credit, per account, per month. The lint's premise is
 * dissolved for these pools and the lint was narrowed rather than deleted.
 *
 * ⛔ **AND IT GRANTED NOTHING AT ALL UNTIL 2026-08-14, WHICH IS THE DEFECT THIS
 * SLICE FOUND RATHER THAN THE ONE IT SET OUT TO FIX** (decision 3421). The
 * allotment was read with `DefaultsRegistry::int('credits.monthly_grant.sms')` —
 * the **platform-settings** accessor. That key is not a platform setting; 3320
 * seeded it as a **per-plan entitlement**, `plan.base.credits.monthly_grant.sms`,
 * *"because what a plan grants is precisely what `plan_entitlements` versions."*
 * So `assertDeclared()` raised on every run, {@see self::allotmentFor()} caught it
 * exactly as designed, and the command printed its fail-closed warning and wrote
 * nothing — **for every tenant, on every daily run, since the day it shipped.**
 *
 * ⚠️ **NOTHING ABOUT THAT WAS VISIBLE, AND THAT IS 272's SHAPE IN ITS PUREST
 * FORM.** The command exited `SUCCESS`. The suite was green — greener than it
 * should have been, because `MonthlyCreditResetTest` asserted the *fail-closed*
 * branch as its main case and its own header said *"the figure is not in this
 * repository yet"*, which had stopped being true when 3320 landed. A ruling
 * recorded in two documents, a seed in the manifest, a command in the scheduler,
 * and no credits: 2660's eighteen hours, arriving through a mistyped accessor
 * rather than a mistyped column. **This class now reads the entitlement through
 * {@see DefaultsRegistry::entitlement()} and a test asserts the balance rather
 * than the warning.**
 *
 * ## What it does, in order, per account
 *
 *   1. **Is this account entitled at all?** {@see SubscriptionStatus::isEntitled()}
 *      — its first caller anywhere in `app/`, and its own docblock says so. A
 *      cancelled account collecting free SMS every month for ever is the failure
 *      this answers. ⛔ **And it did not answer it, because a status is only half
 *      the question** (8961): a cancelled *annual* row is deliberately parked at
 *      `active` with a future end date so the tenant keeps the year they bought
 *      (2748), and nothing ever revisited it — so this granted 500 SMS, 1,000
 *      emails and a month of AI credit every month, for ever, to an account that had
 *      cancelled and whose year was over. `Subscription::accessHasEnded()` is the
 *      other half and is asked beside it below.
 *   2. **What does this account's *plan* grant?** See below — read per account
 *      rather than hardcoded, because only `Plan::Base` has seeded allotments.
 *   3. **Do the trial abuse controls object?** For an account that is not paying,
 *      {@see TrialEligibility::authorize()}. See below.
 *   4. **Per product: has this account already been granted it this month?** Asked
 *      inside {@see CreditLedger::resetMonthly()}, under the business lock, so two
 *      concurrent runs cannot both grant.
 *   5. **Expire, then grant.** Two append-only rows per product, never a deletion.
 *
 * ## Why the plan is read rather than assumed
 *
 * ⚠️ **`PlanCharges` HARDCODES `Plan::Base` AND THIS DELIBERATELY DOES NOT.** That
 * class quotes a *price*, and every checkout this application offers is a Base
 * checkout, so there is no other plan for it to quote. This hands out **500 real,
 * billable SMS**, and 3321 is explicit that Free and Limited have no seeded
 * allotment on purpose: *"seeding `0` for Free would read as a ruling that Free
 * grants nothing, which nobody has made … so there are no rows, `entitlement()`
 * raises for those plans, and the raise is the correct answer until somebody
 * rules."* Hardcoding Base here would **make that ruling by implication** and give
 * a free account the paid account's allotment. Reading the plan lets the raise do
 * its job, and {@see self::allotmentFor()} turns it into a skip and a line of
 * output.
 *
 * ## Why the trial gate is here and not in the ledger's signature
 *
 * ⚠️ **`TrialGrantAuthorization` WAS BUILT TO BE A PARAMETER AND IS NOT ONE, AND
 * THE REASON HAS TO BE WRITTEN DOWN RATHER THAN NOTICED LATER.** Its docblock asks
 * the grant lane to take one, so that a caller cannot skip the controls by
 * deleting an `if` with an empty body — and it says in the same breath that *"no
 * lint can require it"*. The reason it is not a parameter of
 * `CreditLedger::resetMonthly()` is that **it authorizes a trial grant, and a
 * paying tenant's allotment is not one**. `TrialEligibility` refuses any account
 * with no confirmed Google listing; making that a precondition of every grant
 * would withhold a paid entitlement over a fraud signal aimed at free accounts,
 * which is a worse failure than the one the parameter prevents. So the gate lives
 * at the only caller, applied to exactly the accounts it was written for, and this
 * paragraph is the compensating control (3341).
 *
 * ⚠️ **THE GATE IS ASKED ONCE PER ACCOUNT AND NOT ONCE PER PRODUCT** (3419). It
 * answers *"may this account be granted a trial allotment"*, which is a fact about
 * the account; asking it three times would give the same answer three times and
 * invite a future edit that let a refused account keep one of the three.
 *
 * ⚠️ **A REFUSAL IS CAUGHT HERE AND NEVER PROPAGATES** — `TrialGrantRefused`'s own
 * instruction at 3239. Every refusal it carries is deterministic, so a retry
 * cannot improve one; left to escape it would surface as an infrastructure fault
 * over a decision the system made correctly.
 *
 * ## The enumeration
 *
 * Reaching each business through its owner, exactly as `reviews:reinvite` and
 * `oauth:refresh-tokens` do, and for their reason: `businesses` is FORCE ROW LEVEL
 * SECURITY on a policy keyed to the session tenant, so `Business::all()` returns
 * nothing and dropping a global scope does not help, because the policy is in the
 * database. Read `ReinviteDeferredReviews`' docblock before changing this one.
 *
 * ⚠️ **DAILY, NOT MONTHLY, THOUGH IT GRANTS ONCE A MONTH.** A monthly schedule has
 * twelve chances a year to be missed and no way to catch up; a daily one that is
 * idempotent per calendar month self-heals from any outage shorter than a month,
 * and on the other twenty-nine days it reads a few rows per tenant and writes
 * nothing.
 */
#[Signature('credits:reset-monthly')]
#[Description("Expire the lapsed monthly allotments and grant this month's")]
final class ResetMonthlyCredits extends Command
{
    /**
     * The actor on every row this command writes.
     *
     * A system actor string rather than a user id, matching every other scheduled
     * writer — and it is what makes an expiry legible a year later as *the
     * schedule* rather than as somebody's decision.
     */
    private const string ACTOR = 'system:credits-monthly-reset';

    public function __construct(
        private readonly CreditLedger $credits,
        private readonly TrialEligibility $trials,
        private readonly DefaultsRegistry $registry,
        /**
         * ⛔ **HERE FOR ONE RULE — THE FOURTEEN-DAY BOUND ON A NO-CARD TRIAL**
         * (9328). This command reads {@see SubscriptionStatus::isEntitled()}
         * directly and deliberately (8961), and the enum cannot see a clock, so
         * the bound has to be asked of the service or re-derived here — and
         * re-deriving it is how the *last* rule that lived in two places came
         * apart. It is asked with the row this method already holds, so it costs
         * no second query.
         */
        private readonly Subscriptions $subscriptions = new Subscriptions,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $granted = 0;
        $skipped = 0;
        $refused = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$granted, &$skipped, &$refused): void {
                foreach ($users as $user) {
                    [$g, $s, $r] = $this->sweepOwner((int) $user->getKey());

                    $granted += $g;
                    $skipped += $s;
                    $refused += $r;
                }
            });

        // Never leave a security context established after a console command. The
        // PostgreSQL session variable outlives this process's connection under any
        // pooler, and a worker inheriting it would start as whichever tenant the
        // loop happened to touch last.
        Tenancy::forgetAll();

        $this->info(
            "Granted an allotment to {$granted} ".str('account')->plural($granted)
            .", skipped {$skipped}, withheld from {$refused} on the trial controls."
        );

        return self::SUCCESS;
    }

    /**
     * One product's allotment for one plan, in **ledger units**, or null when
     * nothing has configured one.
     *
     * ⛔ **`entitlement()`, NEVER `int()`, AND THE DIFFERENCE IS DECISION 3421.**
     * These keys live in `plan_entitlements` and not in `platform_settings`; the
     * platform-settings accessor raises `InvalidArgumentException` on a key the
     * settings manifest has never heard of, which is precisely what happened
     * silently on every run for two days. The registry's own message is right and
     * was never read: *"a key it has never heard of is a typo far more often than
     * it is a new setting."*
     *
     * ⛔ **AND THE CONVERSION IS {@see CreditProduct::ledgerUnitsFromGrant()}'s,
     * NOT THIS METHOD'S** (3331, 3420). The AI seed is integer **cents** — 5,000
     * for $50 (9180) — and the AI ledger pool is **hundredths of a cent**. Handing
     * 5,000 straight to the ledger grants a tenant 1% of what they were promised, with
     * no exception and no obviously-wrong number anywhere on the way. So this
     * method never does arithmetic on a seed; it asks the product, which is the
     * one place in this application that knows both denominations.
     *
     * ⚠️ **THREE FAILURES ARE CAUGHT AND ALL THREE MEAN THE SAME THING TO AN
     * OPERATOR**: nobody has said what this plan grants of this product. A key the
     * manifest never declared, a key declared without an integer seed, and a key
     * the owner has deliberately {@see DefaultsRegistry} withholds are different
     * causes with one correct response — grant nothing, say which key, and leave
     * the balance the tenant already holds untouched. An expiry without a grant
     * behind it would take credits away on the strength of a missing configuration
     * value, permanently.
     */
    private function allotmentFor(Plan $plan, CreditProduct $product): ?int
    {
        try {
            $seed = $this->registry->entitlement($plan, $product->monthlyGrantKey());
        } catch (InvalidArgumentException|WithheldRegistryValue) {
            return null;
        }

        if (! is_int($seed) || $seed < 1) {
            return null;
        }

        return $product->ledgerUnitsFromGrant($seed);
    }

    /**
     * Every business this user owns, through the `owner_lookup` policy.
     *
     * @return array{int, int, int} granted, skipped, refused
     */
    private function sweepOwner(int $userId): array
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and no
        // tenant is established yet — the same circularity ResolveTenant
        // documents. The database still restricts this to businesses owned by the
        // user just set, so it cannot widen beyond one person's own.
        $businessIds = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->pluck('id');

        $granted = 0;
        $skipped = 0;
        $refused = 0;

        foreach ($businessIds as $businessId) {
            match ($this->sweepBusiness((int) $businessId)) {
                'granted' => $granted++,
                'refused' => $refused++,
                default => $skipped++,
            };
        }

        return [$granted, $skipped, $refused];
    }

    /**
     * One account's reset, across all three products.
     *
     * @return 'granted'|'refused'|'skipped'
     */
    private function sweepBusiness(int $businessId): string
    {
        // Inside the tenant from here down. Every query below is an ordinary
        // scoped Eloquent query with RLS beneath it — no withoutGlobalScope, no
        // raw cross-tenant read.
        Tenancy::set($businessId);

        $business = Business::query()->find($businessId);

        if (! $business instanceof Business) {
            return 'skipped';
        }

        $subscription = Subscription::query()->latest('id')->first();
        $status = $subscription?->status;
        $plan = $subscription?->plan;

        // ⚠️ NO SUBSCRIPTION ROW MEANS NO GRANT, AND IT FAILS CLOSED RATHER THAN
        // OPEN. Provisioning writes a `pending_checkout` row, so an account with
        // none is one this application does not understand — and the thing being
        // handed out is 500 real, billable SMS. `isEntitled()` is generous on
        // purpose (`past_due` and `pending_checkout` both qualify); it is the
        // *absence* of any answer that is refused here. A row with no plan on it
        // is refused for the same reason: nothing says what it is entitled to.
        //
        // ⛔ `noCardTrialHasEnded()` IS ASKED HERE FOR EXACTLY 8961's REASON, ONE
        // RULING LATER (9328). The owner bounded a `pending_checkout` trial at
        // fourteen days from registration, and `SubscriptionStatus` cannot see a
        // clock — its `isEntitled()` still answers true for that status and
        // always will. So the arm that carries the bound lives on the service,
        // and a caller reading the enum directly does not inherit it. **This is
        // the one path that mints 500 real, billable SMS every month**, and it is
        // the path a trial that never ended cost the most on: a verified account
        // that registered, confirmed a listing and never bought anything was
        // granted the full monthly allotment for ever.
        //
        // ⚠️ IT IS ASKED WITH THE ROW ABOVE, NOT WITH A SECOND READ. The service
        // takes the subscription as a parameter precisely so that this caller can
        // consult the rule without re-querying and without a second copy of it.
        //
        // ⛔ `accessHasEnded()` IS ASKED HERE AS WELL AS AT
        // `Subscriptions::isEntitled()`, AND THAT IS NOT BELT AND BRACES — IT IS
        // THE ONLY PLACE IT COULD BE ASKED (8961). This command reads the enum
        // directly rather than the service, because it needs the row anyway for
        // the plan and because a missing row must fail *closed* here where the
        // service fails open. So fixing the service alone would have left the
        // one path that mints 500 real, billable SMS every month still granting
        // them to an account whose paid term ended a year ago. `CLAUDE.md`'s
        // outer-guard shape (398) with the arrow reversed: the inner guard was
        // the one that moved, and this caller would not have felt it.
        if (! $subscription instanceof Subscription
            || ! $status instanceof SubscriptionStatus
            || ! $status->isEntitled()
            || $subscription->accessHasEnded()
            || $this->subscriptions->noCardTrialHasEnded($business, $subscription)
            || ! $plan instanceof Plan) {
            return 'skipped';
        }

        if (! $this->isPayingFor($status)) {
            try {
                // The return value is deliberately unused: holding it is the point.
                // `TrialGrantAuthorization`'s constructor throws on a verdict carrying
                // any refusal, so reaching the next line *is* the control passing.
                $this->trials->authorize($business);
            } catch (TrialGrantRefused $refusal) {
                // Caught rather than propagated (3239): every refusal is deterministic,
                // so no retry can improve one, and an escaping exception would read as
                // an infrastructure fault over a decision made correctly. The reasons
                // are internal fraud signals and go to an operator's console, never to
                // a tenant.
                $this->line("  business {$businessId}: allotment withheld — {$refusal->getMessage()}");

                return 'refused';
            }
        }

        return $this->grantEveryProduct($businessId, $plan) ? 'granted' : 'skipped';
    }

    /**
     * Expire and re-grant each of the three allotments for the tenant in context.
     *
     * ⚠️ **EACH PRODUCT IS INDEPENDENT, WHICH IS STRONGER THAN ONE COMBINED
     * DECISION** (3419). `CreditLedger::resetMonthly()` is idempotent *per product*
     * per calendar month, so a run that grants SMS and then fails before AI leaves
     * the next daily run granting AI alone. A single "already dealt with" flag
     * would have made the same partial run permanent, and it would look exactly
     * like a complete one.
     *
     * ⚠️ **A PRODUCT WITH NO CONFIGURED ALLOTMENT IS SKIPPED AND SAID OUT LOUD**,
     * rather than skipped quietly. It is the state 3421 shows can persist for days
     * while every part of the system reports success.
     *
     * @return bool Whether anything at all was written for this account.
     */
    private function grantEveryProduct(int $businessId, Plan $plan): bool
    {
        $wrote = false;

        foreach (CreditProduct::cases() as $product) {
            $units = $this->allotmentFor($plan, $product);

            if ($units === null) {
                $this->line(
                    "  business {$businessId}: no {$product->value} allotment configured for the "
                    ."{$plan->value} plan (plan.{$plan->value}.{$product->monthlyGrantKey()}), so "
                    .'nothing was granted or expired for it.'
                );

                continue;
            }

            $wrote = $this->credits->resetMonthly($product, $units, self::ACTOR) || $wrote;
        }

        return $wrote;
    }

    /**
     * Whether money is actually changing hands for this account.
     *
     * ⚠️ **THE TRIAL CONTROLS APPLY TO EVERYTHING THAT IS NOT PAYING, WHICH IS
     * WIDER THAN `trialing`.** With the card requirement gone (2065) the real
     * no-card trial state is `pending_checkout` — registered, entitled, and with
     * nothing behind it — and that is precisely the population 2066 called a fraud
     * surface. Reading only `trialing` would let the controls pass over the
     * accounts they were written for.
     *
     * `past_due` counts as paying: it is Stripe retrying a card, usually an
     * expiry, and `SubscriptionStatus::isEntitled()` already refuses to cut those
     * accounts off during the retry window.
     */
    private function isPayingFor(SubscriptionStatus $status): bool
    {
        return match ($status) {
            SubscriptionStatus::Active, SubscriptionStatus::PastDue => true,
            SubscriptionStatus::PendingCheckout, SubscriptionStatus::Trialing => false,
            // Neither is entitled and neither reaches this method; named rather
            // than defaulted so a seventh status is a compile-time conversation.
            SubscriptionStatus::Canceled, SubscriptionStatus::Incomplete => false,
        };
    }
}

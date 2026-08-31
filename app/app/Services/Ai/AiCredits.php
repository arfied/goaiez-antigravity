<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\CreditKind;
use App\Enums\CreditProduct;
use App\Enums\CreditVerdict;
use App\Exceptions\CreditMovementRefused;
use App\Models\AiCall;
use App\Services\Billing\CreditLedger;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Carbon;

/**
 * What an AI call charges the tenant, and what they have been charged this month
 * (decisions 3358–3363).
 *
 * ⛔ **THIS IS THE DEBIT SIDE OF THE AI CREDIT ACCOUNT, AND SINCE 3608 IT IS A
 * CEILING AS WELL AS A METER — BUT ONLY FOR A TENANT WHO HAS BEEN FUNDED.** Said
 * that precisely because a claim of protection is what stops the next reviewer
 * looking (314–316, and 3107 is this very layer's own instance of it). This
 * paragraph read *"a meter rather than a ceiling"* while a paragraph thirteen
 * lines below read *"it bounds the path now"* — both survived a task review inside
 * one docblock, which is 2505's shape at close range (3822).
 *
 * ✅ **THE BALANCE EXISTS NOW AND THIS CLASS DEBITS IT** (3419, 3424). Two things
 * that were missing have landed: the owner answered the denomination (3412 —
 * *retail* credit rather than provider spend, which 9180 did not re-open when it
 * moved the amount, so `credits.monthly_grant.ai_cents` seeds `5000` and leaves
 * `withheld()`), and `credit_ledger` gained a product dimension, so a money pool
 * can exist beside two message pools. {@see self::debitForCall()} is the writer
 * `CreditProduct::Ai` would otherwise have shipped without, which is 272's shape
 * and the thing this codebase keeps repeating.
 *
 * ✅ **AND IT BOUNDS THE FUNDED PATH NOW** (3608, closing 3425 and completing
 * 3295). {@see self::allowsAnotherCall()} is the refusal that was owed: *"a debit
 * is half of a ceiling"*, and this is the other half. 3297's sequence ran to its
 * end — the debit landed first (3424), 3421 fixed a monthly grant that had never
 * granted anything, and the gate came third, because gating on a balance nothing
 * had ever funded would have stopped all AI for every tenant at once.
 *
 * ⛔ **WHICH IS WHY A TENANT WHO HAS NEVER BEEN FUNDED IS NOT REFUSED** (3609). A
 * balance of zero is two different facts — *exhausted* and *never granted* — and
 * only the first is a reason to stop. {@see CreditLedger::everFunded()} tells them
 * apart, so the gate arms itself per account the first time that account receives
 * credit, and the day this shipped it refused nobody. ⚠️ **A third fact hides in
 * the same zero and is answered ahead of both** — *the plan is not active*, which
 * 3824 found by way of a support credit that turned a lapsed tenant's AI off.
 *
 * ⛔ **AND WHY `ai.monthly_cap_per_tenant` IS NOT GONE, WHICH 3608 BELIEVED IT
 * COULD BE** (3820). The escape above is not a corner of the plan ladder — 3612
 * enumerated it as `Plan::Free` and `Plan::Limited`, and that is wrong in both
 * directions. `Subscriptions` provisions every new business as `Plan::Base` /
 * `pending_checkout`; nothing in `app/` writes either of those two plans to a
 * subscription at all; and `ResetMonthlyCredits::sweepBusiness()` grants **no
 * product** to any account `TrialEligibility` refuses, which is every account
 * without a confirmed Google listing. **So every account is unfunded from
 * registration onward**, and 3609's escape would hand each of them unlimited AI
 * for as long as they stay unverified — a state that is free and under the
 * tenant's own control, defeating every abuse control 2066 asked for. The cap is
 * restored as **outer containment behind this gate**.
 *
 * ⛔ **AND IT BINDS THE NEVER-FUNDED ACCOUNT AND NOBODY ELSE — 3820's OWN CLAIM,
 * MADE TRUE AT 3960.** Three docblocks including this one said the two ceilings
 * *"bound different populations and neither covers the other's"* while the cap was
 * read for every tenant alike, and the arithmetic says they overlap: the cap is
 * $50 of **our** cost, which at {@see self::RETAIL_MULTIPLE} is $400 of the
 * tenant's, and the manual AI SKU sells $300 of credit at a time. **A tenant who
 * bought two of them and spent $400 of what they had paid for was refused by the
 * cap while holding a balance the gate had just permitted** — mid-month, with
 * nothing refunded, nothing on any screen (3626) and no clearing until the
 * calendar rolled. That is the limit 3293 deleted, arriving through the back door.
 * {@see AiSpend::refusal()} now asks the cap for {@see CreditVerdict::NeverFunded}
 * and for no other verdict.
 *
 * ⚠️ **SO THE BALANCE IS HONEST ABOUT WHAT IT IS: WHAT REMAINS OF THE GRANT,
 * FLOORED AT ZERO — NOT WHAT WAS SPENT.** The authoritative record of spend is
 * `ai_calls.retail_hundredths_cents`, which {@see self::retailSpentThisMonthHundredths()}
 * sums and which records every call whether or not the ledger could cover it.
 * Reading a zero balance as "this tenant stopped spending" is the error, and it
 * is the reason both numbers exist.
 *
 * ⚠️ **THE UNIT IS HUNDREDTHS OF A CENT AND THIS CLASS READS NO `_cents` KEY**
 * (decision 3372, unchanged and now load-bearing in a second place). Every figure
 * it touches comes from `AiModel`'s per-million-token prices through `AiCall`,
 * which are hundredths of a cent throughout, and the multiple below is
 * dimensionless. The credits registry's grant and top-up SKUs are in **cents**, a
 * hundred times coarser. **The one place the two meet is
 * {@see CreditProduct::ledgerUnitsFromGrant()}**, at the grant, in
 * `ResetMonthlyCredits` — nothing here crosses that boundary, and the ledger pool
 * this class debits is denominated in *this* class's unit precisely so that the
 * debit never has to convert anything.
 *
 * ⚠️ **NOT `AiSpend`, AND THE DIFFERENCE IS THE CURRENCY.** `AiSpend` meters what
 * the *provider* bills us — the cost book, priced from `AiModel` at write time.
 * This meters what the *tenant* is billed, which decision 3304 fixes at eight
 * times our cost: *"Ai credits are priced 8 to 1 what it cost us."* Two books
 * side by side, exactly as `MessageCostLedger` sits beside `SendCredits`, and for
 * the same reason 3105 records — **recording a cost is not debiting a balance**,
 * and a class that did both would let one of them stand in for the other.
 *
 * ⚠️ **THE MULTIPLE IS COMPUTABLE HERE AND NOWHERE ELSE IN THIS APPLICATION**,
 * which is the whole reason this class can exist at all. 3304's warning is that
 * an 8:1 markup is uncomputable against an empty cost book: `messaging.carrier_cost_*`
 * all seed to `0`, `0` short-circuits the write, so `message_cost_entries` is
 * permanently empty and a multiplier applied to it would render *"no rate is
 * configured"* and *"nothing was sent"* as the same figure (3104, 3105). **The AI
 * cost book is the one that is genuinely full**: `AiModel` carries verified vendor
 * prices, `costOf()` is integer arithmetic over real token counts, and every call
 * — answered, refused or failed — already writes a row. So the deliverable 3304
 * asks for, *"a recorded provider cost per call"*, is a fact here rather than a
 * thing to build, and the markup lands on a real number.
 *
 * ⚠️ **EVERY AI CALL IS CHARGED, WITH NO PER-TASK EXEMPTION, AND THE ONE THAT
 * MATTERS IS THE INBOUND CASE** (decision 3361). `SendCredits::debitsACredit()`
 * deliberately exempts `InboundSms` and `InboundMms` — *"charging a tenant
 * because their customer replied is the wrong product"* — and that reasoning is
 * about **receiving a message**, which the tenant did not ask for and cannot
 * decline. An AI turn generated *in response* to an inbound message is the
 * tenant's own automation acting on their behalf: they chose to run it, they can
 * turn it off, and it is the one path 3106 and 3297 both name as the live
 * uncapped instance — *"inbound AI conversation cost driven entirely by somebody
 * else's behaviour."* **An exempt path is an unbounded path**, and with the dollar
 * cap deleted (3293) an exemption there would be spend under a stranger's control
 * with nothing above it. So there is no exemption seam to reach for: the charge
 * is written by `AiSpend` on the same line as the cost, for every task, and a
 * test walks `AiTask::cases()` so a task added later cannot arrive unpriced.
 *
 * ⚠️ **INTEGER ARITHMETIC, IN HUNDREDTHS OF A CENT, END TO END** (`18` §Money
 * handling). A per-call AI charge is small enough that float error would be a
 * meaningful fraction of it.
 *
 * ✅ **BOTH POOLS HAVE FUNDERS NOW, AND THIS PARAGRAPH SAID OTHERWISE UNTIL
 * 2026-08-14** — 2505's shape, in a docblock about the very thing it was wrong
 * about. 3307 gives the shape: a monthly grant that resets at the period
 * boundary, a top-up balance that never expires, and a spend that draws the
 * monthly pool first and spills into top-up when it is exhausted.
 * `ResetMonthlyCredits` grants the monthly half — $50, converted to 500,000
 * hundredths at exactly one boundary (9180). **`CreditPurchases` funds the top-up
 * half**: `TopUpCatalog` carries an AI SKU on both tiers, and 3480 pins the
 * conversion that makes a $300 manual load credit 4,000,000 hundredths rather
 * than 40,000 (9181).
 * ⛔ **What is still true is that support cannot help an exhausted AI account** —
 * `CreditGrants` grants SMS only, for the reason 3426 records, so the remedies are
 * a purchase or the period boundary.
 *
 * ⚠️ **THE AI POOL IS MONEY AND THE OTHER TWO COUNT SENDS — ONE TABLE, THREE
 * UNITS** (3367, resolved at 3419). This class's earlier reading was that
 * `credit_ledger` could not hold it at all, quoting the original migration:
 * *"the movement, in whole credits … NOT money and deliberately not
 * `App\Support\Money` — a credit is a unit of send"*. That was true of a table
 * with one balance, and the objection it raises is real — an AI call costing a
 * fraction of a cent has no honest **whole-credit** delta, since rounding up
 * charges an SMS credit for a call twenty-eight times cheaper and rounding down
 * makes it free. **The answer was not a second book but a unit per product**
 * ({@see App\Enums\CreditUnit}): the AI rows are denominated in hundredths of a
 * cent, so no rounding happens anywhere and no price-per-credit has to be
 * invented. One balance per tenant, three products on it.
 */
final class AiCredits
{
    /**
     * What an AI debit points at, in `credit_ledger.ref_type`.
     *
     * A constant on `OutreachMessage::CREDIT_REFERENCE_TYPE`'s precedent, and the
     * table name rather than the class name for its reason: the row outlives any
     * refactor of the model that wrote it, and somebody reading the ledger a year
     * from now has a table to go and look in.
     */
    public const string REFERENCE_TYPE = 'ai_call';

    public function __construct(
        private readonly DefaultsRegistry $defaults = new DefaultsRegistry,
        private readonly CreditLedger $ledger = new CreditLedger,
    ) {}

    /**
     * The **fallback** for what a tenant is charged per unit of provider cost —
     * decision 3304's 8:1, and no longer the last word on it.
     *
     * ⛔ **THIS WAS A CONSTANT ON PURPOSE AND THE OWNER OVERRULED THE ARGUMENT**
     * (decision 3416). What stood here was: *"the registry holds figures an
     * operator may move without a deploy; a markup is a pricing ruling from the
     * owner."* That is a good reason to keep an operator out of it and **not** a
     * reason to keep the owner out of it — and on 2026-08-14 the owner asked for
     * the opposite in as many words: *"everything need to be adjustable in admin
     * so we can make changes on credits and plan packages as we go stuff does not
     * need to be hard coded."* He is the party the old argument was protecting
     * the figure for.
     *
     * ✅ **THE PROPERTY THAT ARGUMENT WAS ACTUALLY PROTECTING SURVIVES INTACT.**
     * A change prices future calls and leaves history alone, because the charge
     * is written onto the `ai_calls` row at call time and
     * {@see self::retailSpentThisMonthHundredths()} sums that column rather than recomputing
     * `cost × multiple`. Moving the rate in Ops cannot reprice a month that has
     * already been reported — which is the guarantee, and it never depended on
     * the figure being a constant.
     */
    public const int RETAIL_MULTIPLE = 8;

    /**
     * What a call costing us `$providerHundredthsCents` charges the tenant.
     *
     * A negative cost is not representable — `AiModel::costOf()` cannot produce
     * one and the column is unsigned — so it clamps rather than multiplying a
     * nonsense figure into a bigger one.
     *
     * ⚠️ **A ZERO OR NEGATIVE MULTIPLE FALLS BACK RATHER THAN BEING HONOURED.**
     * An admin-editable markup can be set to `0`, and a `0` here would silently
     * make every AI call free while every screen went on reporting a rate — which
     * is 3297's shape wearing a price tag. Charging nothing is a decision that
     * needs its own ruling, not a typo in a settings field.
     */
    public function retailFor(int $providerHundredthsCents): int
    {
        $multiple = $this->defaults->intOr('credits.rate.ai_retail_multiple', self::RETAIL_MULTIPLE);

        if ($multiple < 1) {
            $multiple = self::RETAIL_MULTIPLE;
        }

        return max(0, $providerHundredthsCents) * $multiple;
    }

    /**
     * Take a call's retail charge out of this tenant's AI credit balance.
     *
     * ⛔ **THE WRITER `CreditProduct::Ai` WOULD OTHERWISE HAVE SHIPPED WITHOUT**
     * (3424). A product on a ledger that nothing debits is `sending_health_windows`
     * with a bill attached: every screen reads a balance that never moves, the
     * isolation tests pass perfectly, and the suite is green because every test
     * seeds the counter by hand. `AiSpend::record()` calls this on the same line as
     * the charge it writes to `ai_calls`, for every task, so there is no exemption
     * seam to reach for.
     *
     * ⛔ **A REFUSAL IS ABSORBED AND THE CALL IS STILL RECORDED — 2904, AND IT IS
     * THE HALF THAT COULD BE MISREAD AS SLOPPINESS.** By the time this runs the
     * provider has already answered and already billed us; refusing here cannot
     * un-make that, and throwing would take down a queued job over a balance. Rule
     * 43's surviving half (3294) is graceful degradation, never hard-fail.
     * ✅ **What changed at 3608 is what happens NEXT**: the exhausted tenant's
     * *following* call is refused by {@see self::allowsAnotherCall()}, so an
     * absorbed refusal is the end of a series rather than the start of an
     * unbounded one.
     *
     * ⛔ **AND WHEN THE FULL CHARGE CANNOT BE COVERED, WHAT IS LEFT IS TAKEN**
     * (3611). A spend is refused **whole** (3470), which for a text is right —
     * half a message is not sent — and for money leaves *dust*: a grant of 300,000
     * spent in charges of 800-odd ends at a balance somewhere under one call's
     * price, and that residue can never be spent. **A gate reading `balance > 0`
     * would then permit for ever on a balance that can never move again**, which
     * is `sending_health_windows` with a bill attached and would have made the
     * whole of 3608 decoration. Draining is honest here in a way it would not be
     * for a send: the pool is money, the authoritative record of what the tenant
     * was charged is `ai_calls.retail_hundredths_cents` — which this class's own
     * docblock already says the balance is not — and the balance's meaning is
     * *what remains of the grant, floored at zero*.
     *
     * ⚠️ **THE SHORTFALL IS NOT CARRIED, AND THAT IS A DECISION RATHER THAN AN
     * OMISSION.** A tenant charged 984 with 864 left pays 864 and the last 120 is
     * never taken, even if a top-up arrives the next day. An unpaid remainder
     * stored somewhere would be a debt this product does not have, and it would be
     * a second balance to reconcile against the first.
     *
     * ⚠️ **THE DRAIN READS THE *SPENDABLE* BALANCE, NEVER THE WHOLE ONE.** 3441
     * makes purchased credit unspendable while the plan is inactive, so an account
     * holding only top-up would otherwise have this method ask for money the
     * ledger is about to refuse, once per call, for ever.
     *
     * ⚠️ **A ZERO CHARGE MOVES NOTHING AND IS NOT AN ERROR.** `CreditLedger`
     * refuses a movement of zero — *"it is what a writer with a silently-empty
     * argument leaves behind"* — and a genuinely free call (a cached embedding, a
     * model priced at nothing) is not that. It is filtered here rather than
     * relaxing the ledger's rule for everyone.
     *
     * ⚠️ **NO CONVERSION HAPPENS HERE AND NONE MAY BE ADDED.** `$retailHundredths`
     * is already in {@see CreditProduct::Ai}'s ledger unit, which is why that unit
     * was chosen — see {@see App\Enums\CreditUnit::HundredthsOfACent}. A `/ 100`
     * or a `* 100` anywhere in this method is decision 3331 arriving.
     *
     * @param  int  $retailHundredths  What the tenant is charged, in hundredths of
     *                                 a cent — the figure written to
     *                                 `ai_calls.retail_hundredths_cents`.
     * @return bool Whether the balance actually moved.
     */
    public function debitForCall(int $retailHundredths, AiCall $call): bool
    {
        if ($retailHundredths < 1) {
            return false;
        }

        if ($this->takeFromBalance($retailHundredths, $call)) {
            return true;
        }

        // ⛔ THE DRAIN (3611). The full charge was refused, so take what is
        // spendable and leave the pool at zero — otherwise the residue sits below
        // every future charge and the gate above never fires. Re-read rather than
        // computed from the refusal: another movement may have landed in between,
        // and `spendableBalance()` is the same figure the draw order will use.
        //
        // ⛔ CLAMPED TO THE CHARGE, AND THE CLAMP IS NOT BELT-AND-BRACES (3823).
        // The re-read above was reasoned about as though only *debits* could land
        // in the window, which makes the balance smaller and the drain a no-op.
        // A **credit** landing there inverts it: a manual AI top-up of $300 turns
        // a call charged 800 into a single movement of 3,000,800 against that one
        // call. `credit_ledger` is append-only, so the repair is a compensating
        // `Adjust` somebody has to notice first — and 3611's own contract is that
        // "one call's overshoot is bounded by one call's price", which without
        // this line it is not. A tenant is never charged more than the call cost.
        $remaining = min(
            $this->ledger->spendableBalance(CreditProduct::Ai),
            $retailHundredths,
        );

        if ($remaining < 1) {
            return false;
        }

        // A second refusal is possible and is not an error: a concurrent debit
        // may have taken the remainder between the read and the write, or 3441's
        // active-plan gate may refuse the only pool holding anything. Either way
        // the call is already recorded and the balance is already at its floor.
        return $this->takeFromBalance($remaining, $call);
    }

    /**
     * One attempt at moving `$hundredths` out of the AI balance for `$call`.
     *
     * Extracted so the full charge and {@see self::debitForCall()}'s drain are the
     * same movement with a different size — a second `record()` call spelled out
     * twice is two places for the product, the kind or the reference to drift, and
     * the reference is what ties a debit to the call that caused it.
     */
    private function takeFromBalance(int $hundredths, AiCall $call): bool
    {
        try {
            $this->ledger->record(
                product: CreditProduct::Ai,
                kind: CreditKind::Consume,
                delta: -$hundredths,
                actor: 'system',
                reason: null,
                refType: self::REFERENCE_TYPE,
                refId: (int) $call->getKey(),
            );
        } catch (CreditMovementRefused) {
            return false;
        }

        return true;
    }

    /**
     * Whether this tenant can pay for another AI call.
     *
     * ⛔ **THIS IS HALF OF A CEILING AND MUST NOT BE READ AS THE WHOLE ONE**
     * (3608, corrected at 3820). It is the refusal 3425 said was owed — 3293
     * deleted the per-tenant dollar cost cap, 3295 named the balance as its
     * successor, 3297 forbade the swap until the replacement actually refused
     * something, and it does now. **But it bounds only a tenant who has been
     * funded**, and the other half of the population is bounded by
     * `ai.monthly_cap_per_tenant` in {@see AiSpend::refusal()}, which is the caller
     * every path goes through. **Nothing may be inferred about a tenant's ceiling
     * from this method alone.**
     *
     * ⚠️ **AND IT COLLAPSES A DISTINCTION ITS CALLER NEEDS, WHICH IS WHY
     * {@see self::verdict()} EXISTS BESIDE IT** (3960). *"Funded but exhausted"*
     * and *"never funded"* are both `false` and `true` respectively here, and the
     * cap has to tell them apart — a boolean that answers *whether* without
     * answering *why* is what let the cap be read for every tenant alike.
     *
     * ## The two facts a balance of zero cannot tell apart
     *
     * ⛔ **A TENANT WHO HAS NEVER BEEN FUNDED IS PERMITTED, AND THIS IS THE ORDER
     * 3425 REFUSED TO GET WRONG** (3609). *"Gating on the balance before the first
     * successful reset would stop all AI for every tenant at once"* — and 3421
     * records that `credits:reset-monthly` had never granted anything to anybody,
     * so on the day this shipped **every** account in production read zero.
     * {@see CreditLedger::everFunded()} separates *exhausted* from *never
     * granted*: the first refuses, the second does not, and the gate arms itself
     * per account the first time that account is granted, bought or adjusted AI
     * credit. ⚠️ **THIS CITED AN `everFundedSpendably()` THAT DOES NOT EXIST** —
     * it was 3824's rejected first fix, deleted from `app/` and left standing in
     * this sentence, where it read as a method somebody could go and check
     * (3960). The scoping it describes is the thing that ruling refused.
     *
     * ⛔ **THE ESCAPE IS THE WHOLE INSTALL BASE, NOT TWO PLAN TIERS, AND THAT IS
     * WHY IT IS NOT LEFT UNBOUNDED** (3820, correcting 3612). 3612 recorded the
     * cost of the escape as `Plan::Free` and `Plan::Limited`. Verified against
     * `app/`, that is wrong in both directions: nothing writes either plan to a
     * subscription, `Subscriptions` provisions every new business as `Plan::Base` /
     * `pending_checkout`, and `ResetMonthlyCredits::sweepBusiness()` grants **no
     * product at all** to any account `TrialEligibility` refuses — which is every
     * account with no confirmed Google listing, plus the claimed-listing and
     * signup-burst refusals. **So an account is unfunded from registration until it
     * both verifies and survives a daily reset, and staying unfunded is free and
     * under the tenant's own control.** Refusing them all AI here would make
     * 3321's withheld ruling by implication from the other direction, so this
     * method is unchanged and the containment is the restored cost cap one class
     * over. **Whether an unfunded account should get AI at all is the owner's, and
     * it is written down as a question rather than answered here.**
     *
     * ⚠️ **IT CANNOT DISARM.** `credit_ledger` is append-only, so `everFunded()`
     * goes false → true exactly once and never back. A tenant cannot spend their
     * way out of the gate by returning to a zero balance. ⛔ **Which is why the
     * escape it leaves open must be bounded by something else rather than by
     * narrowing this** — 3824 tried narrowing it and reopened 3610.
     *
     * ## What it does not do
     *
     * ⚠️ **NO ESTIMATE OF WHAT THE CALL WILL COST**, because the token count does
     * not exist until the request has been built and answered. So the last call a
     * tenant makes may overshoot what they hold, and {@see self::debitForCall()}
     * takes what is left rather than nothing — see 3611. One call's overshoot is
     * bounded by one call's price, which is true because the drain is clamped to
     * the charge (3823) and was not true before it was.
     *
     * ⚠️ **IT ASKS THE SPENDABLE BALANCE, NOT THE HELD ONE** (3610). Purchased
     * credit is unspendable while the plan is inactive (3441), so an account
     * holding only top-up would otherwise be permitted for ever while every debit
     * behind it was refused for ever.
     *
     * ## A credit can never make a tenant worse off
     *
     * ⛔ **THE INACTIVE PLAN IS ASKED AT ALL, AND THAT IS THE WHOLE OF 3824.**
     * Before it was asked, a support operator's goodwill credit **turned a lapsed
     * tenant's AI off**: `CreditKind::Adjust` lands in the top-up pool
     * ({@see CreditKind::poolWhenCredited()}) and is deliberately exempt from
     * 3441's spend gate, so the credit armed `everFunded()` while
     * `spendableBalance()` — which omits that pool on an inactive plan — stayed at
     * zero. The account was refused **because** it had been credited, and an
     * identical uncredited account was permitted. Strictly worse than doing
     * nothing, with nothing on any screen saying so.
     *
     * ✅ **ASKING THE PLAN DISSOLVES IT RATHER THAN TRADING IT.** An inactive
     * account is refused whatever its funding history, so a credit cannot move the
     * verdict at all; an active account counts every pool in
     * `spendableBalance()`, so a credit always raises it above zero and the verdict
     * moves only toward *permitted*. **The property holds in both plan states and
     * for every `CreditKind`, and it is the one under test.**
     *
     * ⚠️ **THE ORDER OF THE PLAN READ AND THE FUNDING READ IS COSMETIC, AND SAYING
     * SO IS THE POINT.** This docblock claimed the order was load-bearing until the
     * mutation run showed it is not: swapping the two produces `¬everFunded ∧
     * planActive` either way, and the suite stays green — correctly, because it is
     * the same function. **What is load-bearing is that the plan read is there at
     * all**, and deleting it reddens the two tests named for it. A claim of
     * protection that a mutation cannot falsify is 314–316's shape, so it is
     * written as what it is.
     *
     * ⚠️ **THE THREE READS THIS SECTION ARGUES ABOUT LIVE IN
     * {@see CreditLedger::spendVerdict()} SINCE 3960 AND EVERY WORD OF THE ARGUMENT
     * STILL APPLIES TO THEM.** They moved so that the plan is read once instead of
     * twice and so that the *reason* survives the call; none of them changed, and
     * deleting the plan read there reddens the same two tests it always did.
     *
     * ⛔ **THE ALTERNATIVE — SCOPING `everFunded()` TO THE SPENDABLE POOLS — WAS
     * BUILT FIRST AND REJECTED** (3824). It removes the asymmetry too, but it
     * removes it by *permitting* the lapsed top-up-only account, which is exactly
     * the state 3610 exists to refuse: permitted for ever while every debit behind
     * it is refused for ever. It also makes the arming non-monotonic. **Two wrongs
     * pointing the same direction is not a fix**, and the refusing direction was
     * available.
     *
     * ⚠️ **REFUSING AN INACTIVE ACCOUNT IS NOT 3441 ARRIVING AS EXPIRY.** Nothing
     * is taken and nothing lapses: the balance is intact, {@see self::balanceHundredths()}
     * still reports it, and the credits are *waiting*. What is withheld is the
     * spending of it, which is what 3441 rules and what the ledger was already
     * doing per movement.
     *
     * ⚠️ **IT NEVER THROWS FOR A BUDGET REASON** — 2904 and rule 43's surviving
     * half (3294). The reads it makes can still raise `TenantNotResolved`, exactly
     * as the cap read beside it does, because an AI call outside a tenant context
     * is a bug rather than a budget.
     */
    public function allowsAnotherCall(): bool
    {
        return $this->verdict()->permitsSpending();
    }

    /**
     * Which of the states a zero balance hides this tenant is in — the same three
     * reads, with the answer kept rather than collapsed (decision 3960).
     *
     * ⛔ **THE CALLER THAT NEEDS MORE THAN A BOOLEAN IS THE COST CAP.**
     * {@see AiSpend::refusal()} applies `ai.monthly_cap_per_tenant` to
     * {@see CreditVerdict::NeverFunded} and to nothing else, because that is the
     * one state a balance cannot bound — and *"funded but exhausted"* and *"never
     * funded"* are both `false` from {@see self::allowsAnotherCall()} and both
     * `0` from {@see self::balanceHundredths()}. **The distinction was already
     * being made here and was being thrown away on the way out**, which is how a
     * cap described as bounding only the unfunded came to refuse funded tenants
     * too (3960).
     *
     * ⚠️ **AND IT IS WHAT LETS A REFUSAL NAME ITSELF IN THE LOG** (3962). An
     * operator reading *"ai credit exhausted"* against a tenant holding $250 of
     * purchased credit on a lapsed plan is being told the wrong thing; the verdict
     * separates that from a genuinely empty account, and the two have different
     * remedies.
     *
     * ⚠️ **THE READS AND THEIR ORDER ARE {@see CreditLedger::spendVerdict()}'s
     * NOW.** They moved rather than changed: 3441's rule stays beside the draw
     * order, `app/Services/Ai` still re-derives nothing, and the subscription is
     * read once per gate instead of twice (3963).
     */
    public function verdict(): CreditVerdict
    {
        return $this->ledger->spendVerdict(CreditProduct::Ai);
    }

    /**
     * What is left of this tenant's AI credit, in hundredths of a cent.
     *
     * Both pools added — the monthly grant and the top-up — because a caller
     * asking "how much AI credit does this tenant hold" means all of it.
     * `CreditLedger` applies the monthly-first draw order when the spend actually
     * happens. ⚠️ **THIS PARAGRAPH SAID THE TOP-UP POOL "HAS NO FUNDER YET", AND
     * 3619 IS THE ROW THAT CORRECTED IT** — forty lines above, in the same file,
     * and this copy was missed on the way past: `CreditPurchases` funds it through
     * `TopUpCatalog`'s AI SKU on both tiers. Two contradictory statements about
     * the same pool inside one class is 2505's shape at the shortest range it
     * comes in, and **a correction is not a sweep**: the file a correction is made
     * in is the one most worth re-reading afterwards.
     *
     * ⚠️ **IT IS WHAT REMAINS, NOT WHAT WAS SPENT, AND THE TWO STOP AGREEING THE
     * MOMENT A TENANT RUNS OUT.** {@see self::debitForCall()} absorbs a refusal, so
     * this floors at zero while `ai_calls` goes on recording real charges —
     * {@see self::retailSpentThisMonthHundredths()} is the number to read for
     * spend.
     *
     * ⛔ **THIS IS WHAT THEY HOLD; IT IS NOT WHAT THE GATE ASKS** (3610).
     * {@see self::allowsAnotherCall()} reads `CreditLedger::spendableBalance()`,
     * which omits purchased credit while the plan is inactive (3441). The two
     * agree for every active account and differ for exactly the account that would
     * otherwise be permitted for ever on money it cannot spend. **This method is
     * the one to put on a screen** — a tenant holding $250 of top-up on a lapsed
     * plan holds $250, and 3441's own wording is that the credits are *waiting*,
     * not gone.
     */
    public function balanceHundredths(): int
    {
        return $this->ledger->balance(CreditProduct::Ai);
    }

    /**
     * What this tenant has been charged for AI in the current calendar month, in
     * hundredths of a cent.
     *
     * Summed from the charge written on each row rather than from
     * `cost_hundredths_cents * 8`, so that changing {@see self::RETAIL_MULTIPLE}
     * cannot reprice a month that has already been reported.
     *
     * Scoped by `AiCall`'s global scope, so it is this tenant's charge and cannot
     * silently become the platform's — the same reason `AiSpend` resolves the
     * tenant before it reads a total.
     */
    public function retailSpentThisMonthHundredths(): int
    {
        return (int) AiCall::query()
            ->inMonthOf(Carbon::now())
            ->sum('retail_hundredths_cents');
    }
}

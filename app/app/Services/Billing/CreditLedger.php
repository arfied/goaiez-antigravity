<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Console\Commands\ResetMonthlyCredits;
use App\Enums\CreditKind;
use App\Enums\CreditPool;
use App\Enums\CreditProduct;
use App\Enums\CreditUnit;
use App\Enums\CreditVerdict;
use App\Exceptions\CreditMovementRefused;
use App\Models\Business;
use App\Models\CreditLedgerEntry;
use App\Services\Support\CreditGrants;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * The one place this application reads or moves a tenant's credit balance
 * (`29` §11.2 row 20, `DATA-MODEL` §Credits & broadcasts).
 *
 * An `ArchitectureTest` lint makes this the only file in `app/` permitted to touch
 * `CreditLedgerEntry` at all, on `AuditService`/`AuditExplorer`'s precedent (625).
 * The wrong answer it forbids is the attractive one: a screen or a resource
 * computing `SUM(delta)` for itself, which is a second definition of the balance —
 * it agrees with this one until a writer gets `balance_after` wrong, and then
 * disagrees silently, because both look right on their own.
 *
 * ## Three products, two pools, six balances (decisions 3298, 3307, 3419)
 *
 * ⚠️ **THIS CLASS COUNTED SMS SENDS UNTIL 2026-08-14 AND NOW HOLDS THREE
 * PRODUCTS.** 3298 grants three separate allotments a month, per account: **500
 * SMS · 1,000 emails · $50 of AI credit** (9180, reversing 3412's $30). 3307
 * splits each into a monthly pool
 * that expires at the period boundary and a top-up pool that never does. That is
 * **six running balances on one append-only table**, and four things follow:
 *
 *   **The balance is per product and per pool.** `balance_after` on the head row
 *   is *that product and that pool's* running total. {@see self::balance()} takes
 *   a product it cannot default and, optionally, a pool — with no pool it adds
 *   that product's two heads, which is 3315's *"not one number but two
 *   balances."*
 *
 *   **A spend draws monthly first and spills into top-up, within one product**
 *   ({@see CreditPool::drawOrder()}), so a spend can write **two rows**. That is
 *   why {@see self::record()} returns the last row it wrote rather than the only
 *   one, and why nothing may infer the size of a movement from a single returned
 *   entry.
 *
 *   **The monthly pool expires by being written against.** The table is
 *   append-only — the model throws on `updating()` and `deleting()` — so
 *   {@see self::resetMonthly()} writes a `CreditKind::Expire` and then a
 *   `CreditKind::Grant`, per product. Nothing is ever removed, so *"used their
 *   allotment"* and *"let it lapse"* stay distinguishable, which two integers on a
 *   subscription row never could (286, and 3244/3315 on why those two columns are
 *   not the store).
 *
 *   ⛔ **AND THERE IS NO TOTAL.** No method here adds the six together, and none
 *   may be added. {@see CreditProduct::Sms} counts messages and
 *   {@see CreditProduct::Ai} counts hundredths of a cent, so their sum is a
 *   number with no meaning that would nonetheless render perfectly on a screen.
 *   The product parameter is **required and undefaulted** precisely so that the
 *   nonsense is unwritable rather than merely discouraged.
 *
 * ## The unit varies by product, and one conversion boundary exists
 *
 * ⛔ **SMS AND EMAIL ARE WHOLE SENDS. AI IS MONEY AT HUNDREDTHS OF A CENT**
 * ({@see CreditUnit}), because `ai_calls.retail_hundredths_cents` is what debits
 * it and one call charges a fraction of a cent. Everything in this class works in
 * ledger units and knows nothing about cents. **The single place a cents figure
 * becomes a ledger figure is {@see CreditProduct::ledgerUnitsFromGrant()}**, at
 * the caller that reads the registry seed — because the registry states the AI
 * grant in *cents* (`credits.monthly_grant.ai_cents` = 5000, decision 9180) and
 * 3331 predicted by name what reading that straight in would do: **under-grant by
 * a hundred, with the balance looking entirely plausible throughout.**
 *
 * ⚠️ **SO `resetMonthly()` TAKES LEDGER UNITS AND SAYS SO IN ITS SIGNATURE.** It
 * cannot check the denomination of a bare integer, and a method that accepted
 * "the allotment" would be a second place the question gets answered.
 *
 * ⚠️ **`record()`'s SIGNATURE IS LOAD-BEARING BEYOND THIS FILE.** It gained a
 * leading `CreditProduct` and every caller moved with it — deliberately a
 * required first parameter rather than an optional trailing one, because a
 * default would have silently kept every existing caller on SMS and the email and
 * AI debits are exactly the callers that must not be. **Which *pool* a spend comes
 * out of is still not a caller's decision**: it follows from the kind and from
 * what is available, both of which are known here and only here. The one caller
 * that genuinely needs to *restrict* a draw reaches {@see self::recordFromPool()}
 * instead, and there is exactly one: SMS broadcasting, which 3309 confines to the
 * purchased pool.
 *
 * ⚠️ **THIS SAID "THE FUNDER IS STILL ABSENT" UNTIL 2026-08-14 AND IT IS NOW
 * FALSE** — which is 314–316's shape, so it is corrected here rather than left to
 * be discovered. {@see CreditPurchases} sells a top-up on both gateways and is
 * the only file in `app/` permitted to construct `CreditKind::Purchase`; all three
 * products can be bought. A support operator's `Adjust` ({@see CreditGrants}) is
 * still SMS-only and still the only *operator* path, so the consequence 3426
 * recorded holds in a narrower form: support cannot hand out email or AI credit,
 * and a tenant who wants some buys it.
 *
 * ⛔ **AND CREDIT IS SPENDABLE ONLY WHILE THE PLAN IS RUNNING** (3441 for the
 * purchased pool, 9330 for the granted one). *"Top up credits never expire but
 * they need an active plan to use them."* It is a gate on the **draw**, applied
 * here rather than in any caller — {@see self::planPermitsThisMovement()} — and
 * it is emphatically not an expiry: both balances survive an inactive plan
 * untouched and become spendable again the moment the plan does.
 *
 * ⛔ **THIS SAID "PURCHASED CREDIT" AND COVERED HALF THE BALANCE UNTIL 2026-08-25**
 * (9330). `legsFor()` filtered `CreditPool::TopUp` out of the draw order and let
 * the movement continue against the granted pool, so an account with no running
 * plan spent its full monthly allotment — 500 texts, 1,000 emails and $50 of AI
 * credit — while the gate above it read as closed and the tenant's own credit
 * screen said their credit could not be spent. The owner ruled on 2026-08-25 that
 * the granted pool respects entitlement too, and the refusal now covers both.
 * ⚠️ **What has not changed is which *kinds* are gated**: only a `Consume`, and
 * never a `Refund`, an `Adjust` or any credit (6397).
 *
 * ⚠️ **THE *MONTHLY* HALF HAS A REAL WRITER FOR ALL THREE PRODUCTS**:
 * `CreditKind::Grant` is written by {@see ResetMonthlyCredits}, once per product
 * per calendar month, because 3298 and 3412 set every figure that 502's
 * withheld-value posture was protecting.
 */
final class CreditLedger
{
    public function __construct(
        /**
         * ⚠️ **HERE FOR ONE RULE AND ONE RULE ONLY — 3441's ACTIVE-PLAN GATE ON
         * SPENDING TOP-UP CREDIT.** It is the first dependency this class has ever
         * had, and the `BroadcastPreconditions` tripwire reddened on its arrival,
         * which is that assertion doing its job: a constructor is public surface.
         * Defaulted rather than required, because {@see SendCredits},
         * {@see EmailCredits} and `AiCredits` all construct this class with `new`.
         */
        private readonly Subscriptions $subscriptions = new Subscriptions,
    ) {}

    /**
     * One product's balance for this tenant, whole or by pool.
     *
     * The head row's `balance_after` per product and pool, never a SUM — the head
     * is authoritative. An empty ledger is **0**, not null: a tenant who has never
     * been granted or bought a credit has none, which is a knowable fact rather
     * than a missing one.
     *
     * ⛔ **THE PRODUCT HAS NO DEFAULT AND MUST NEVER BE GIVEN ONE** (3419). Before
     * the products existed this method answered with no arguments at all, and
     * every caller meant SMS because SMS was all there was. A default of
     * `CreditProduct::Sms` would have left all of them compiling and correct while
     * `EmailCredits` and the AI debit — the two callers this whole slice exists
     * for — silently went on spending the text balance. The compiler error at each
     * call site *is* the migration.
     *
     * ⚠️ **OMITTING THE POOL IS THAT PRODUCT'S WHOLE BALANCE, WHICH IS WHAT A
     * SENDER MEANS.** `SendCredits::canAffordOneSend()` asks "can this tenant send
     * a text", and the answer is that product's two pools added. Passing a pool is
     * for the caller that needs the two apart — 3309's broadcast guard, and the
     * draw order in this file.
     *
     * ⚠️ `latest('id')`, never `latest()`. The default orders by `created_at`,
     * which is nullable here, and Postgres sorts NULL **first** on a descending
     * order — so an undated row would become the head and the tiebreak would never
     * run. That is decision 289, which appeared three times in one slice and is now
     * an `ArchitectureTest` lint. In an append-only ledger the id *is* the order
     * things happened in.
     */
    public function balance(CreditProduct $product, ?CreditPool $pool = null): int
    {
        Tenancy::idOrFail();

        if ($pool instanceof CreditPool) {
            return $this->poolBalance($product, $pool);
        }

        $total = 0;

        foreach (CreditPool::cases() as $case) {
            $total += $this->poolBalance($product, $case);
        }

        return $total;
    }

    /**
     * What of one product's balance this tenant could actually spend right now.
     *
     * ⛔ **{@see self::balance()} IS WHAT THEY HOLD; THIS IS WHAT THEY CAN SPEND,
     * AND SINCE 3441 THE TWO DIFFER** (decision 3610). Purchased credit is
     * spendable only while the plan is active, so an inactive account holding
     * $250 of top-up has a balance of $250 and **nothing** it can spend. A gate
     * that asked `balance()` would therefore permit for ever while every debit
     * behind it was refused for ever — 272's writerless control reached through a
     * reader that answers the wrong question.
     *
     * ⛔ **IT APPLIES 3441's RULE BY DELEGATING TO IT, NEVER BY RESTATING IT.**
     * The ruling puts that gate *"in `CreditLedger` beside the draw order, not in
     * each of the five callers"*, and a second copy of it here — even inside the
     * same class — would be the drift that ruling forbids. {@see self::planIsActive()}
     * is the one answer and {@see self::legsFor()} skips the same pool for the
     * same reason.
     *
     * ⚠️ **IT IS NOT A PROMISE THAT ANY PARTICULAR MOVEMENT WILL SUCCEED.** A
     * spend is refused whole (3470), so a caller holding fewer units than the
     * movement it is about to make still gets nothing — see
     * `AiCredits::debitForCall()`, which is why the AI debit takes what is left
     * rather than leaving dust behind a gate that would then never fire.
     *
     * ⚠️ **THE POOL PARAMETER MIRRORS {@see self::balance()}'s AND EXISTS FOR ONE
     * CALLER** (3825): 3309's broadcast guard, which may spend purchased credit and
     * only purchased credit. Naming `CreditPool::TopUp` here asks *"how much
     * purchased balance can this tenant actually spend"*, which is the question a
     * guard in front of a send has to ask; `balance(Sms, TopUp)` answers the
     * different question of how much they hold, and on a lapsed plan the two
     * differ by the whole amount.
     *
     * ⚠️ **THIS METHOD NEVER ASSERTS THE TENANT ON ITS OWN LINE, UNLIKE
     * {@see self::balance()} — AND WHAT PROTECTS IT IS NOT WHAT THIS PARAGRAPH
     * FIRST CLAIMED** (3827). The claim was that reading
     * {@see self::planPermitsSpending()} *before* the branch is what performs
     * `Tenancy::idOrFail()`, so sinking it into the `TopUp` arm would leave the
     * `Monthly` arity answering with no tenant. **The mutation ran green**, because
     * `TenantScope::apply()` calls `idOrFail()` on every read of
     * `CreditLedgerEntry` and {@see self::poolBalance()} refuses by itself. So
     * does the reverse mutation. **Two independent guards, neither individually
     * load-bearing**, which is 398's shape read from the safe side — and it means
     * `CreditLedgerTest`'s *"refuses to read a balance with no tenant
     * established"* can only be driven red by removing both at once, which is how
     * it was driven. **The property is what is pinned; the mechanism is not
     * claimed**, because an unfalsifiable claim of protection is 314–316's shape
     * and this paragraph was an instance of it for an hour.
     */
    public function spendableBalance(CreditProduct $product, ?CreditPool $pool = null): int
    {
        return $this->spendableGiven($this->planPermitsSpending(), $product, $pool);
    }

    /**
     * Why this tenant may or may not spend one product's credit right now.
     *
     * ⛔ **THE THREE FACTS A ZERO BALANCE CANNOT TELL APART, ANSWERED IN ONE PLACE
     * AND WITH EACH READ MADE ONCE** (decision 3960). The gate above this used to
     * ask {@see self::spendableBalance()}, then {@see self::planPermitsSpending()},
     * then {@see self::everFunded()} — and the first of those asks the plan for
     * itself, so the subscription was read twice on every call for the whole of
     * today's install base, which is unfunded by construction. Here the plan is
     * read once and handed to the balance.
     *
     * ⛔ **AND IT IS WHY THE CALLER CAN TELL *exhausted* FROM *never granted* FROM
     * *lapsed*.** {@see CreditVerdict} carries the reason, so a caller can refuse
     * the first two, permit the third (3609), and name which one it was in a log
     * that an operator will read against a balance that may look perfectly healthy.
     * A boolean could do none of that, and the AI cost cap depends on the
     * distinction: it is the ceiling for exactly the accounts a balance cannot
     * bound — `AiSpend::refusal()`, its only caller today.
     *
     * ⚠️ **THE ORDER IS NOT COSMETIC HERE, THOUGH IT WAS IN THE CALLER IT
     * REPLACES.** A spendable balance answers first, because an active account with
     * credit is permitted whatever its history; the plan answers next, because 3824
     * requires that an inactive account is refused *whatever* its funding history,
     * or a support credit can turn a lapsed tenant's AI off by arming
     * {@see self::everFunded()} without moving what they can spend.
     */
    public function spendVerdict(CreditProduct $product): CreditVerdict
    {
        $active = $this->planPermitsSpending();

        if ($this->spendableGiven($active, $product, null) > 0) {
            return CreditVerdict::Spendable;
        }

        if (! $active) {
            return CreditVerdict::PlanInactive;
        }

        return $this->everFunded($product)
            ? CreditVerdict::Exhausted
            : CreditVerdict::NeverFunded;
    }

    /**
     * {@see self::spendableBalance()}, with 3441's answer already in hand.
     *
     * ⛔ **THE ONE DEFINITION OF *spendable*, AND THE REASON IT IS A PARAMETER
     * RATHER THAN A SECOND READ.** {@see self::spendVerdict()} needs the plan and
     * the balance together, and re-asking for each would be two subscription reads
     * per gate. It is private and takes the answer from inside this class only —
     * a caller able to pass its own `$active` would be a way to spend purchased
     * credit on a lapsed plan by asserting it was not, which is the rule 3441 puts
     * here precisely so that no caller holds it.
     *
     * ⚠️ **IT WALKS {@see CreditPool::drawOrder()} AND NOT `cases()`** (3961). This
     * is the reader a gate consults, and the writer it has to agree with is
     * {@see self::legsFor()}, which walks the draw order. The two match today
     * because there are exactly two pools; a third added to the enum and not to the
     * draw order would make this over-report, the drain compute a remainder
     * `legsFor()` could not cover, and the pool sit as permanent dust behind a gate
     * that never fires — 3611's failure reached through the reader instead of the
     * writer. {@see self::balance()} keeps `cases()` deliberately: *held* means
     * every pool, whether or not a spend can reach it.
     */
    private function spendableGiven(bool $active, CreditProduct $product, ?CreditPool $pool): int
    {
        // ⛔ EVERY POOL, NOT JUST `TopUp` — THE OWNER'S RULING OF 2026-08-25
        // (9330). This asked `$pool === CreditPool::TopUp && ! $active` until
        // today, which mirrored `legsFor()` exactly and was therefore right about
        // the writer and wrong about the rule: an unentitled account's *granted*
        // allotment was spendable in full. The reader and the writer still agree,
        // which is the property this method exists for; what moved is both of
        // them together.
        if (! $active) {
            return 0;
        }

        if ($pool instanceof CreditPool) {
            return $this->poolBalance($product, $pool);
        }

        $total = 0;

        foreach (CreditPool::drawOrder() as $case) {
            $total += $this->poolBalance($product, $case);
        }

        return $total;
    }

    /**
     * Whether this tenant's plan currently permits spending purchased credit —
     * 3441's condition, asked about the tenant in context rather than about a
     * movement.
     *
     * ⛔ **IT IS PUBLIC BECAUSE A GATE HAS TO ASK IT, AND THE ALTERNATIVE WAS A
     * SECOND COPY OF THE RULE IN A CALLER** (decision 3824). 3441 puts the
     * active-plan condition *"in `CreditLedger` beside the draw order, not in each
     * of the five callers"*, and `AiCredits::allowsAnotherCall()` needs to tell an
     * inactive account apart from an unfunded one. Exposing the one answer is what
     * that ruling asks for; re-deriving `isEntitled()` in `app/Services/Ai` is what
     * it forbids.
     *
     * ⚠️ **"ACTIVE" IS DELIBERATELY GENEROUS AND THAT IS LOad-BEARING FOR 3609.**
     * {@see Subscriptions::isEntitled()} counts `past_due` **and
     * `pending_checkout`**, so a brand-new account that has not paid, not verified
     * and never been granted anything is *active* by this answer. A gate built on
     * it therefore cannot mistake "the reset has not reached me yet" for "my plan
     * lapsed" — which is the whole reason it can be used to close 3824 without
     * reopening 3609.
     */
    public function planPermitsSpending(): bool
    {
        $businessId = Tenancy::idOrFail();

        return $this->planIsActive(
            Business::query()->whereKey($businessId)->first()
        );
    }

    /**
     * Whether this tenant has **ever** been given credit of one product.
     *
     * ⛔ **THE DIFFERENCE BETWEEN "EXHAUSTED" AND "NEVER GRANTED", WHICH A
     * BALANCE OF ZERO CANNOT TELL YOU** (decision 3609). Both read `0`, and a gate
     * that treats them alike refuses a tenant the monthly reset has simply not
     * reached yet. 3421 records that `credits:reset-monthly` had **never granted
     * anything to anybody**, so on the day a balance gate ships the second reading
     * is the true one for every account in production.
     *
     * ⚠️ **`delta > 0` RATHER THAN `kind = grant`, AND THE WIDENING IS THE POINT.**
     * A tenant who has *bought* AI credit has been funded just as surely as one who
     * was granted it, and so has one a support operator adjusted upward. Asking
     * only about `Grant` would leave a tenant who bought a top-up, spent it and
     * never received a monthly allotment permanently ungated — the hole this
     * method exists to close, reached through the narrower question.
     *
     * ⚠️ **IT IS MONOTONIC AND THAT IS WHAT MAKES IT SAFE.** The table is
     * append-only, so the answer goes false → true exactly once per product per
     * tenant and can never go back. A gate built on it arms itself the first time
     * an account is funded and cannot silently disarm.
     *
     * ⛔ **IT COUNTS EVERY POOL, INCLUDING ONE THE TENANT CANNOT CURRENTLY SPEND
     * FROM, AND A CALLER MUST NOT USE IT ALONE TO REFUSE** (3824). Scoping it to
     * the spendable pools was tried and rejected: it would have made the answer
     * non-monotonic and, worse, would have handed a lapsed account holding only
     * purchased credit the *permitted* verdict 3610 exists to prevent. The
     * inactive-plan case is {@see self::planPermitsSpending()}'s to answer, ahead
     * of this one — see `AiCredits::allowsAnotherCall()` for the ordering and why
     * it is not interchangeable.
     */
    public function everFunded(CreditProduct $product): bool
    {
        Tenancy::idOrFail();

        return CreditLedgerEntry::query()
            ->where('product', $product)
            ->where('delta', '>', 0)
            ->exists();
    }

    /**
     * How much of one product's credit has already been taken back against one
     * reference — a positive number of ledger units.
     *
     * ⛔ **A `SUM`, IN THE ONE FILE PERMITTED TO WRITE ONE, AND IT IS NOT A
     * BALANCE.** `Architecture\BillingTest` holds {@see CreditLedgerEntry} to this
     * class precisely so that nothing else computes `SUM(delta)` and creates a
     * second definition of the balance — *"they agree until a writer gets
     * `balance_after` wrong, and then disagree silently"*. This is a different
     * quantity and the distinction is the whole justification for the method
     * existing: **the head row's `balance_after` cannot answer it.** A balance is
     * one number per product and pool; *"how much has been reversed against
     * purchase 41"* is a fact about a subset of rows, and no head row is a head of
     * that subset.
     *
     * ⛔ **AND IT IS THE SECOND IDEMPOTENCY LAYER FOR A CLAWBACK, ON EXACTLY THE
     * ARGUMENT `CreditPurchases` MAKES FOR A CREDIT.** There, a partial unique
     * index makes a second `Purchase` row for one purchase impossible whatever the
     * service says, *because two different notifications can describe one
     * payment*. A reversal cannot use that index — a purchase may legitimately be
     * refunded twice, in parts — so the equivalent protection is arithmetic: the
     * caller computes what the reversal is worth in total and subtracts what it
     * has already taken, so a second event describing the same reversal moves
     * nothing. See {@see CreditClawbacks::reverse()}.
     *
     * ⚠️ **THE SIGN IS FLIPPED ON THE WAY OUT AND THAT IS DELIBERATE.**
     * {@see CreditKind::Refund} is unconstrained in sign on purpose — its docblock
     * says so and `credit_ledger_sign_matches_kind` deliberately omits it — so a
     * future credit-back would be a positive row and *reduce* how much stands
     * reversed. Returning "units taken back" rather than "sum of deltas" is what
     * makes that arithmetic read correctly at the caller instead of needing a
     * negation there, where somebody would eventually drop it.
     *
     * ⚠️ **IT COUNTS EVERY POOL.** `Refund`'s draw order is top-up alone today, so
     * scoping the read to that pool would agree with the writer and stop agreeing
     * the moment either moved — and this figure's job is to describe the whole of
     * what has been reversed, not the part of it one pool happened to fund.
     */
    public function reversedUnitsFor(CreditProduct $product, string $refType, int $refId): int
    {
        Tenancy::idOrFail();

        return -(int) CreditLedgerEntry::query()
            ->where('product', $product)
            ->where('kind', CreditKind::Refund)
            ->where('ref_type', $refType)
            ->where('ref_id', $refId)
            ->sum('delta');
    }

    /**
     * Move one product's balance and record why.
     *
     * The pool is chosen here, from the kind and from what is available: a credit
     * lands in `CreditKind::poolWhenCredited()`, and a debit walks
     * `CreditKind::drawOrder()` taking what each pool can cover until the movement
     * is filled. **The product is not chosen here** — it is what the caller is
     * paying for, and only the caller knows whether it just sent a text, an email
     * or an AI turn.
     *
     * ⚠️ **A SPEND MAY WRITE TWO ROWS AND THIS RETURNS THE LAST OF THEM.** One
     * credit taken from a monthly pool with nothing left in it is one row; taken
     * from a monthly pool holding less than the movement, it is a monthly row and
     * a top-up row. The return exists so a caller can read `reason`, `ref_id` or
     * the row's identity — **never so it can read the size of the movement**,
     * which is the caller's own `$delta` and is the only place it is whole.
     *
     * @param  int  $delta  ⚠️ **In `$product`'s own ledger units**
     *                      ({@see CreditProduct::unit()}): whole sends for SMS and
     *                      email, **hundredths of a cent** for AI. Negative spends.
     *                      A cents figure passed here for AI is out by a hundred
     *                      and will look plausible — see
     *                      {@see CreditProduct::ledgerUnitsFromGrant()}.
     *
     * @throws CreditMovementRefused
     */
    public function record(
        CreditProduct $product,
        CreditKind $kind,
        int $delta,
        string $actor,
        ?string $reason = null,
        ?string $refType = null,
        ?int $refId = null,
    ): CreditLedgerEntry {
        return $this->move($product, $kind, $delta, $actor, $reason, $refType, $refId, null);
    }

    /**
     * Move one product's balance within one named pool, and nowhere else.
     *
     * ⛔ **THIS EXISTS FOR ONE RULING AND SHOULD NOT ACQUIRE A SECOND CALLER
     * CASUALLY.** 3309 kept exactly one clause of the old two-pool rule and
     * hardened it: SMS broadcasting spends the purchased pool only, and *"monthly
     * sms credits can not be used for this."* Everything else — review invites,
     * missed-call text-back, the chat bot, every email, every AI turn — draws that
     * product's two pools in {@see CreditPool::drawOrder()}'s order and must go
     * through {@see self::record()}.
     *
     * ⚠️ **RESTRICTING A DRAW IS NOT THE SAME AS CHOOSING WHERE A CREDIT LANDS.**
     * A positive movement still has to agree with its kind: naming the monthly
     * pool for a `Purchase` is refused here and by
     * `credit_ledger_purchase_is_top_up`, because paid credit with an expiry date
     * on it is unrecoverable in an append-only table.
     *
     * @param  int  $delta  In `$product`'s own ledger units; negative spends.
     *
     * @throws CreditMovementRefused when the named pool cannot cover the movement,
     *                               or when it contradicts the kind.
     */
    public function recordFromPool(
        CreditProduct $product,
        CreditPool $pool,
        CreditKind $kind,
        int $delta,
        string $actor,
        ?string $reason = null,
        ?string $refType = null,
        ?int $refId = null,
    ): CreditLedgerEntry {
        return $this->move($product, $kind, $delta, $actor, $reason, $refType, $refId, $pool);
    }

    /**
     * Expire what is left of this period's allotment of one product, and grant the
     * next one.
     *
     * ⚠️ **PER PRODUCT, AND IDEMPOTENT PER PRODUCT PER CALENDAR MONTH.** 3419
     * makes each of the three allotments its own set of rows, so each is granted
     * and expired independently. That is stronger than one combined flag would
     * have been: if the AI grant refuses for any reason while the SMS grant
     * succeeds, the next daily run grants the AI half alone rather than deciding
     * the tenant has already been dealt with.
     *
     * ⚠️ **THE EXPIRY COMES FIRST AND THE ORDER IS NOT COSMETIC.** Granting first
     * would add this month's allotment to last month's remainder and then expire
     * the combined figure, so a tenant who used none of theirs would end the reset
     * with nothing. Expiring first is also what makes the two rows readable a year
     * later: the `Expire` row's `delta` is exactly what the tenant did not use.
     *
     * ⚠️ **UNDER THE SAME LOCK EVERY MOVEMENT TAKES.** The schedule fires daily so
     * that a missed run self-heals, so this is asked far more often than it acts.
     * Two concurrent runs cannot both grant: the check and the write share one
     * transaction and the lock on the business row, which is the same race
     * `record()` documents and closes.
     *
     * ⚠️ **THE PERIOD IS THE CALENDAR MONTH, WHICH IS A READING OF THE RULING AND
     * IS FLAGGED AS ONE.** The owner wrote *"on month CREdits reset each month"* —
     * a month, not a billing anniversary. `subscriptions.current_period_end`
     * exists and would give an anniversary instead, and the two differ for every
     * tenant who did not register on the first. Taking the anniversary would make
     * the reset depend on a subscription row that a `pending_checkout` tenant does
     * not meaningfully have; taking the calendar month makes it depend on nothing.
     * It is recorded at 3345 rather than settled, because it is a rule about when
     * money-shaped things expire.
     *
     * @param  int  $units  ⚠️ **THIS PERIOD'S ALLOTMENT IN `$product`'s LEDGER
     *                      UNITS, ALREADY CONVERTED.** Sends for SMS and email;
     *                      **hundredths of a cent** for AI, which the registry
     *                      states in cents. The caller converts through
     *                      {@see CreditProduct::ledgerUnitsFromGrant()} and this
     *                      method cannot check that it did — an integer carries no
     *                      denomination, which is exactly why the conversion has
     *                      one home and a test that reddens on a factor of a
     *                      hundred.
     * @return bool Whether anything was written — false when this tenant has
     *              already been granted this product inside the current calendar
     *              month.
     *
     * @throws CreditMovementRefused
     */
    public function resetMonthly(CreditProduct $product, int $units, string $actor): bool
    {
        $businessId = Tenancy::idOrFail();

        if ($units < 1) {
            throw CreditMovementRefused::because(
                "An allotment of {$units} {$product->value} units is not an allotment. A period "
                .'that grants nothing should not write a grant row saying it did — '
                .'and a negative one would be a debit wearing the wrong kind.'
            );
        }

        return DB::transaction(function () use ($product, $units, $actor, $businessId): bool {
            Business::query()->whereKey($businessId)->lockForUpdate()->first();

            if ($this->grantedInCurrentPeriod($product)) {
                return false;
            }

            foreach (CreditPool::cases() as $pool) {
                if (! $pool->expiresAtPeriodBoundary()) {
                    continue;
                }

                $remaining = $this->poolBalance($product, $pool);

                if ($remaining > 0) {
                    $this->move(
                        $product,
                        CreditKind::Expire,
                        -$remaining,
                        $actor,
                        'The monthly allotment lapsed at the period boundary.',
                        null,
                        null,
                        $pool,
                    );
                }
            }

            $this->move($product, CreditKind::Grant, $units, $actor, null, null, null, null);

            return true;
        });
    }

    /**
     * The head row's balance for one product and pool, with no tenancy check of
     * its own.
     *
     * Private and unguarded on purpose: every public entry point above has already
     * called `Tenancy::idOrFail()`, and repeating it inside a loop would say
     * nothing new while making the failure appear at a different depth each time.
     */
    private function poolBalance(CreditProduct $product, CreditPool $pool): int
    {
        return (int) (CreditLedgerEntry::query()
            ->where('product', $product)
            ->where('pool', $pool)
            ->latest('id')
            ->value('balance_after') ?? 0);
    }

    /**
     * Whether this tenant has already been granted this product inside the current
     * month.
     *
     * ⚠️ **THE LEDGER IS ITS OWN BOOKKEEPING AND THAT IS DELIBERATE.** The obvious
     * alternative is a `last_granted_at` column on `businesses` or `subscriptions`,
     * and it would be a second store for a fact this table already records
     * perfectly — 286's shape, and the one that loses is whichever a reader happens
     * to ask. It also means the idempotency key cannot drift out of step with the
     * grant it guards, because it *is* the grant.
     *
     * ⚠️ **PREDICATED ON THE PRODUCT, WHICH IS THE HALF A READER WOULD SKIM PAST**
     * (3419). Without that clause the first product granted in a month would make
     * the other two look already dealt with, and a tenant would receive 500 SMS
     * and no email or AI credit at all — for ever, silently, with a green suite,
     * because every one of these methods would still be doing exactly what it
     * says.
     */
    private function grantedInCurrentPeriod(CreditProduct $product): bool
    {
        $lastGrant = CreditLedgerEntry::query()
            ->where('product', $product)
            ->where('kind', CreditKind::Grant)
            // `latest('id')` for decision 289's reason, one column over from the
            // one being read: `created_at` is nullable, and Postgres sorts NULL
            // first on a descending order — so an undated row would become the
            // head and this would answer about the wrong grant.
            ->latest('id')
            ->value('created_at');

        // A tenant with no grant at all, or one whose head grant predates this
        // column being written, is due one. Fail *open* here and only here: the
        // cost of granting twice is one allotment, the cost of never granting is a
        // product that does not send.
        if (! $lastGrant instanceof DateTimeInterface) {
            return false;
        }

        return CarbonImmutable::instance($lastGrant)
            ->greaterThanOrEqualTo(CarbonImmutable::now()->startOfMonth());
    }

    /**
     * One movement, as one or two rows, under the business lock.
     *
     * @param  ?CreditPool  $restrictTo  The only pool this movement may touch, or
     *                                   null to follow the kind's own draw order.
     *
     * @throws CreditMovementRefused
     */
    private function move(
        CreditProduct $product,
        CreditKind $kind,
        int $delta,
        string $actor,
        ?string $reason,
        ?string $refType,
        ?int $refId,
        ?CreditPool $restrictTo,
    ): CreditLedgerEntry {
        $businessId = Tenancy::idOrFail();

        $this->refuseIncoherent($kind, $delta, $reason, $refType, $refId);

        /*
         * ⚠️ THE LOCK IS ON THE BUSINESS ROW, NOT ON THE LEDGER'S HEAD, AND THAT
         * IS THE POINT. Reading the head and then inserting is a race: two
         * concurrent movements both read balance 100 and both write 90, so one
         * spend vanishes and `balance_after` is wrong from then on — in an
         * append-only table, permanently.
         *
         * `SELECT ... FOR UPDATE` on the head row cannot close it, because the
         * first movement for a business locks an empty set and therefore locks
         * nothing. The business row always exists, so locking it serialises every
         * movement for that tenant including the first. Decision 350 is the
         * counter-example worth naming: duplicate collapse stayed best-effort
         * because closing it needed a migration that slice did not have room for.
         * This one has the room, and a wrong balance cannot be repaired by editing
         * a row back.
         *
         * ⚠️ ONE LOCK FOR ALL SIX BALANCES, WHICH IS COARSER THAN IT NEEDS TO BE
         * AND IS THE RIGHT TRADE. An AI debit and an SMS debit for the same tenant
         * now serialise against each other though they touch different rows. The
         * alternative is a lock per (product, pool), which is four more lock
         * objects and a deadlock ordering to get right, to save contention on a
         * table written a few times a minute per tenant at its very busiest.
         *
         * ⚠️ AND IT IS WHAT MAKES A TWO-ROW SPEND ATOMIC. Since 3307 a single
         * credit can be taken half from the monthly pool and half from top-up; the
         * two rows are one movement, and a reader that saw only the first would see
         * a tenant charged and a message unpaid for.
         */
        return DB::transaction(function () use ($product, $kind, $delta, $actor, $reason, $refType, $refId, $businessId, $restrictTo): CreditLedgerEntry {
            $business = Business::query()->whereKey($businessId)->lockForUpdate()->first();

            $entry = null;

            $planPermits = $this->planPermitsThisMovement($business, $kind, $delta);

            foreach ($this->legsFor($product, $kind, $delta, $restrictTo, $planPermits) as [$pool, $legDelta]) {
                $entry = $this->write($product, $pool, $kind, $legDelta, $actor, $reason, $refType, $refId);
            }

            // No `?? throw` guarding this. `legsFor()` returns a `non-empty-list`,
            // so the loop above always runs at least once and Larastan knows it —
            // and a guard that cannot be driven red by mutation is decision 398's
            // unfalsifiable inner check, which reads as protection and is not.
            return $entry;
        });
    }

    /**
     * Whether credit of any pool may be spent on this movement — 3441 for the
     * purchased pool, 9330 for the granted one.
     *
     * ⛔ **"TOP UP CREDITS NEVER EXPIRE BUT THEY NEED AN ACTIVE PLAN TO USE
     * THEM."** The first half was already true; this is the second, and it is a
     * gate on **spending** and not an expiry. Nothing is written against either
     * pool, nothing is deleted, and a tenant who starts a plan finds both balances
     * exactly as they left them — which is why the refusal in
     * {@see self::legsFor()} says *waiting* and never *expired*.
     *
     * ⛔ **THE NAME SAID `topUpIsSpendable` AND THAT WAS THE WHOLE DEFECT** (9330).
     * It was an accurate name for what it did and a misleading one for what a
     * reader took it to mean: an unentitled account was refused the pool it had
     * *paid* for and permitted the pool it had been *given*, which is the more
     * expensive half and the half a fraud script reaches. The rule is the same
     * rule; what moved is which pools it reaches.
     *
     * ⛔ **IT LIVES HERE AND NOT IN THE FIVE CALLERS, AND THE RULING SAYS SO IN AS
     * MANY WORDS**: *"it belongs in `CreditLedger` beside the draw order, not in
     * each of the five callers, or it becomes five rules that drift."* The senders,
     * the invite path, the email meter, the AI debit and the broadcast guard all
     * inherit it without knowing it exists.
     *
     * ## What it gates, and the three things it deliberately does not
     *
     * ⚠️ **ONLY `Consume`.** A spend is what the ruling is about.
     *
     *   `Expire`   is the monthly pool's own reset. ⛔ **THE EXEMPTION SURVIVES
     *              9330 AND ITS ARGUMENT DOES NOT, WHICH IS WORTH MORE THAN AN
     *              EXEMPTION NOBODY RE-READ.** It said *"it never touches top-up
     *              anyway, so gating it would be a rule that cannot fire"* —
     *              256's lint that matches nothing — and its draw order is
     *              `[CreditPool::Monthly]`, which is exactly the pool 9330 just
     *              gated. **So the rule would now fire.** It stays exempt on a
     *              different argument: an expiry is the ledger clearing its own
     *              stale allotment before writing the next one, which is
     *              bookkeeping rather than a spend, and a period boundary that
     *              could not clear the previous period would leave the next
     *              grant computed against a month nobody paid for.
     *   `Refund`   takes credit back **because the money went back to them**.
     *              Refusing it on an inactive plan would leave a tenant holding
     *              credit they have been repaid for, which is the gate helping
     *              somebody keep what they did not pay for.
     *   `Adjust`   is an operator correction, and a negative one is usually a
     *              clawback of exactly that kind. ⚠️ **Support must be able to act
     *              on a cancelled account** — that is when most corrections
     *              happen — and a ledger primitive refusing an operator is a
     *              support surface with no override.
     *
     * ⚠️ **A CREDIT IS NEVER GATED**, whatever its kind: money arriving is not
     * money being spent, and refusing a purchase on an inactive plan would take
     * somebody's payment and give them nothing.
     *
     * ## Who counts as active
     *
     * ⚠️ **`Subscriptions::isEntitled()`, WHICH IS DELIBERATELY GENEROUS AND IS
     * THE SAME ANSWER THE MONTHLY GRANT USES** (3346). `pending_checkout`,
     * `trialing`, `active` and `past_due` all qualify; `canceled` and `incomplete`
     * do not. **`past_due` qualifying is the load-bearing part**: it is Stripe's
     * retry window, hours to days, and routinely an expired card rather than a
     * refusal to pay — cutting off credit somebody has already bought, during a
     * retry, would be the hardest failure to explain in the product.
     *
     * ⚠️ **AND A BUSINESS WITH NO ROW AT ALL IS ACTIVE**, which is `isEntitled()`'s
     * fail-open and is right here for the same reason it is right there: the cost
     * of guessing wrong is refusing to spend credit a paying customer bought, on
     * the strength of a row that was never written.
     *
     * ⛔ **AND SINCE 2026-08-25 `pending_checkout` QUALIFIES FOR FOURTEEN DAYS AND
     * NOT FOR EVER** (9328). The paragraph above is the enum's answer and it is
     * unchanged; what `isEntitled()` now adds on top of it is a clock over
     * `businesses.created_at`. **3609 is not reopened by that** — the account that
     * ruling protects is one the monthly reset has not reached *yet*, which is by
     * definition inside its first fortnight — and this is the one arm of that
     * argument a reader is likeliest to mis-size, because the enum still answers
     * `true` and always will.
     */
    private function planPermitsThisMovement(?Business $business, CreditKind $kind, int $delta): bool
    {
        if ($delta > 0 || $kind !== CreditKind::Consume) {
            return true;
        }

        return $this->planIsActive($business);
    }

    /**
     * Whether 3441's active-plan condition is met, with no reference to any
     * particular movement.
     *
     * Extracted so that {@see self::spendableBalance()} asks the *same* question
     * the draw order asks rather than a second copy of it — a reader and a writer
     * disagreeing about who may spend purchased credit would show up as a gate
     * that permits and a debit that refuses, which is the failure with no symptom.
     *
     * ⚠️ **A MISSING BUSINESS ROW FAILS OPEN, HERE AND ONLY HERE.** `move()` has
     * locked the row and found nothing, which is an inconsistency this rule has no
     * standing to punish a tenant for, and every other guard in this file still
     * applies. `Subscriptions::isEntitled()` makes the same choice for a business
     * with no subscription row at all.
     */
    private function planIsActive(?Business $business): bool
    {
        if (! $business instanceof Business) {
            return true;
        }

        return $this->subscriptions->isEntitled($business);
    }

    /**
     * How a movement divides across one product's pools, as `[pool, delta]` pairs.
     *
     * A credit is one leg: the kind decides where it lands. A debit walks the
     * kind's draw order taking what each pool can cover, which is 3307's spill-over
     * — *"monthly runs out it takes from the top up pools"* — and it is the only
     * place in this application where that order is applied.
     *
     * ⚠️ **THE ORDER IS APPLIED WITHIN A PRODUCT AND NEVER ACROSS ONE** (3419).
     * `poolBalance()` is asked for this product's pool, so an exhausted email
     * allotment cannot spill into the SMS one — which would be the same failure
     * 3309 forbids for broadcasts, arriving through arithmetic rather than through
     * a caller.
     *
     * ⛔ **AND SINCE 3441 A SPEND MAY NOT REACH THE TOP-UP POOL AT ALL WHEN THE
     * PLAN IS INACTIVE.** *"Top up credits never expire but they need an active
     * plan to use them."* That is a gate on **spending**, not an expiry: the
     * balance is untouched, no row is written against it, and it becomes spendable
     * again the moment the plan is. See {@see self::planPermitsThisMovement()}, and
     * note the refusal wording below — *waiting*, never *expired*.
     *
     * ⛔ **AND SINCE 2026-08-25 THE GRANTED POOL IS GATED TOO** (9330), so an
     * inactive plan reaches **no** pool and this method refuses before it walks
     * anything. The paragraph above is kept because its argument is unchanged and
     * now carries twice the balance.
     *
     * @param  bool  $planPermits  Whether **any** pool may be drawn from at all on
     *                             this movement — 3441 for the purchased pool and
     *                             9330 for the granted one.
     * @return non-empty-list<array{CreditPool, int}>
     *
     * @throws CreditMovementRefused
     */
    private function legsFor(
        CreditProduct $product,
        CreditKind $kind,
        int $delta,
        ?CreditPool $restrictTo,
        bool $planPermits,
    ): array {
        if ($delta > 0) {
            $lands = $kind->poolWhenCredited();

            if ($restrictTo instanceof CreditPool && $restrictTo !== $lands) {
                throw CreditMovementRefused::because(
                    "A {$kind->value} belongs to the {$lands->value} pool, so it cannot be "
                    ."added to {$restrictTo->value}. Credit landing in the wrong pool either "
                    .'expires when it was paid for or survives when it was granted, and '
                    .'neither can be corrected by editing the row back.'
                );
            }

            return [[$lands, $delta]];
        }

        if (! $planPermits) {
            /*
             * ⛔ THE 2026-08-25 RULING, AND IT REPLACES THE FILTER THAT USED TO
             * STAND HERE (9330). Until today this dropped `CreditPool::TopUp` out
             * of the draw order and let the movement carry on against the granted
             * pool — so an account with no running plan spent its full monthly
             * allotment of texts, emails and AI, and 3441's gate covered the
             * smaller half of the balance. The owner ruled that the granted pool
             * respects entitlement too. Neither pool is drawable now, so there is
             * nothing left to walk and the honest thing is to say why here rather
             * than to fall through to an arithmetic refusal about a balance.
             *
             * ⛔ NOTHING IS EMPTIED AND NOTHING EXPIRES, WHICH IS 3441's WORDING
             * AND IS NOW LOAD-BEARING FOR TWO POOLS RATHER THAN ONE. An
             * append-only ledger cannot un-write an expiry, so a gate implemented
             * as one would destroy money the tenant paid for the moment their
             * plan stopped. Both balances sit exactly where they are and become
             * spendable again the instant the plan does — which for the account
             * this most often means, a fourteen-day trial that ran out, is one
             * Checkout away.
             *
             * ⛔ AND IT IS A SEPARATE REFUSAL FROM "below zero" BECAUSE THE TWO
             * ARE DIFFERENT FACTS WITH DIFFERENT REMEDIES. Run out: buy more.
             * Plan not running: the credit is there, start the plan and it is
             * spendable. Collapsing them would tell somebody holding 500 texts
             * and $250 of purchased credit that they had run out, which is the
             * sentence support would be asked about.
             */
            throw CreditMovementRefused::because(
                "A movement of {$delta} {$product->value} units needs a running plan. Credit is "
                .'spendable only while the plan is running — the credit the plan includes as '
                .'well as the credit that was bought. The credits are waiting: nothing has '
                .'expired and nothing has been taken away, and they become spendable again as '
                .'soon as the plan is.'
            );
        }

        $order = $restrictTo instanceof CreditPool ? [$restrictTo] : $kind->drawOrder();

        $remaining = -$delta;
        $legs = [];

        foreach ($order as $pool) {
            if ($remaining === 0) {
                break;
            }

            $take = min($this->poolBalance($product, $pool), $remaining);

            if ($take > 0) {
                $legs[] = [$pool, -$take];
                $remaining -= $take;
            }
        }

        // `$legs === []` is not redundant with `$remaining > 0` for a reader or for
        // static analysis: it is what proves this method's `non-empty-list` return
        // to Larastan, and the return is what lets `move()` hand back a row it
        // knows exists rather than a nullable one.
        //
        // ⚠️ THE 3441 REFUSAL USED TO SIT HERE AND HAS MOVED UP RATHER THAN GONE
        // (9330). It was reached only when the draw ran short *after* `TopUp` had
        // been filtered out, and there is nothing left to filter now: an inactive
        // plan is refused before the walk. Its wording is carried forward whole,
        // because the wording is the ruling.
        if ($remaining > 0 || $legs === []) {
            // ⚠️ THE WORDING IS PART OF THE CONTRACT. `PlatformMessageSender`
            // catches this into `SendOutcome::refused(…, InsufficientCredit)`,
            // `ReviewInviteSender` rolls its whole send back on it (2904), and
            // `AiSpend` absorbs it so a call that already happened is still
            // recorded — an exhausted balance degrades, it never hard-fails. The
            // phrase "below zero" is asserted by tests that predate the pools.
            throw CreditMovementRefused::because(
                "A movement of {$delta} {$product->value} units would take the balance below "
                .'zero. A tenant who has run out has run out; an overdraft is not '
                .'something this application extends on its own.'
            );
        }

        return $legs;
    }

    /**
     * One row, with the running total this service computed under the lock.
     */
    private function write(
        CreditProduct $product,
        CreditPool $pool,
        CreditKind $kind,
        int $delta,
        string $actor,
        ?string $reason,
        ?string $refType,
        ?int $refId,
    ): CreditLedgerEntry {
        $entry = new CreditLedgerEntry;
        $entry->fill([
            'delta' => $delta,
            'kind' => $kind,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'reason' => $reason,
            'created_by' => $actor,
        ]);

        // Guarded, so all three are assigned here rather than filled: the running
        // total is this service's to compute under the lock above, never a
        // caller's to assert, and the same is true of the pool it is a total of
        // (3307) and of the product that decides which total it is and what unit
        // that total is in (3419).
        $entry->balance_after = $this->poolBalance($product, $pool) + $delta;
        $entry->pool = $pool;
        $entry->product = $product;
        $entry->save();

        return $entry;
    }

    /**
     * The refusals that belong above the database.
     *
     * Every one is also a CHECK constraint. Both layers are wanted for decision
     * 216's reason — the constraint catches the repair script that reached neither
     * the enum nor this method, and this gives a caller an error naming the rule
     * instead of SQLSTATE 23514.
     *
     * @throws CreditMovementRefused
     */
    private function refuseIncoherent(
        CreditKind $kind,
        int $delta,
        ?string $reason,
        ?string $refType,
        ?int $refId,
    ): void {
        if ($delta === 0) {
            throw CreditMovementRefused::because(
                'A movement of zero credits records nothing. It is what a writer '
                .'with a silently-empty argument leaves behind, and afterwards it '
                .'cannot be told apart from a deliberate no-op.'
            );
        }

        if ($kind->isAlwaysCredit() && $delta < 0) {
            throw CreditMovementRefused::because(
                "A {$kind->value} always increases the balance, so {$delta} is "
                .'incoherent. If credits are being taken back, that is a refund or '
                .'an adjustment and should say so.'
            );
        }

        if ($kind->isAlwaysDebit() && $delta > 0) {
            throw CreditMovementRefused::because(
                "A {$kind->value} always decreases the balance, so {$delta} is "
                .'incoherent.'
            );
        }

        if ($kind->requiresReason() && ($reason === null || trim($reason) === '')) {
            throw CreditMovementRefused::because(
                "A {$kind->value} must carry a reason. It has no ref_type to "
                .'explain it, so the sentence is the only record of why the balance '
                .'moved — and support will be asked.'
            );
        }

        if (($refType === null) !== ($refId === null)) {
            throw CreditMovementRefused::because(
                'A reference is both ref_type and ref_id or neither. Half of one is '
                .'a pointer at nothing, and it fails at read time rather than here.'
            );
        }
    }
}

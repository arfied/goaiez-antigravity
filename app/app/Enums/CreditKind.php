<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a `credit_ledger` row moved the balance (`DATA-MODEL` §Credits & broadcasts).
 *
 * ⚠️ **A STRING COLUMN CAST TO THIS ENUM, NEVER A POSTGRES ENUM TYPE**, which is
 * decision 863. `DATA-MODEL` declares the column as `kind credit_kind` — a native
 * enum type — and `CLAUDE.md` forbids one outright, with an `ArchitectureTest`
 * lint failing the build on `->enum(` in any migration. The deviation is recorded
 * rather than silent because `DATA-MODEL` is the schema authority, so a reader
 * comparing the two would otherwise think the code had drifted. The reasons that
 * survived the move to Postgres are the load-bearing ones: a database enum is a
 * second source of truth that drifts from this file, and its values can never be
 * dropped or reordered once added.
 *
 * ⚠️ **TWO POOLS EXIST AND THIS ENUM SPANS BOTH — BUT WHICH POOL A ROW LANDS IN
 * IS NO LONGER THIS ENUM'S SECRET.** It is `credit_ledger.pool`, cast to
 * {@see CreditPool}, and the mapping from a kind to its pool is
 * {@see self::poolWhenCredited()} and {@see self::drawOrder()} below.
 *
 * ⚠️ **THIS DOCBLOCK SAID `Grant` "MUST NOT BE USED TO MINT A PLAN ALLOWANCE"
 * UNTIL 2026-08-13, AND THE PREMISE UNDER IT IS GONE** (3298, 3307). It refused
 * on decision 502's posture — the allotment's size was open question F, a ledger
 * row is append-only, and so the guess would have become the record. **The owner
 * has since set the figure**: 500 SMS per account per month, confirming 2060
 * unchanged. The refusal is therefore lifted for this pool and *only* for this
 * pool — see {@see self::Grant}, and see {@see CreditPool} for why the AI
 * allotment is still not something this table can express at all.
 */
enum CreditKind: string
{
    /**
     * Credits bought outright — the purchased pool, and the only pool row 20
     * calls settled. Always increases the balance.
     */
    case Purchase = 'purchase';

    /**
     * Credits spent. Always decreases the balance.
     *
     * ⚠️ **THIS SAID "NOTHING IN THIS APPLICATION CONSUMES A CREDIT YET" UNTIL
     * 2026-08-13, AND BOTH HALVES WERE FALSE** (decision 3109). Two senders
     * consume a credit — `PlatformMessageSender`, reached from `RunCampaignJob`,
     * and the review-invite path, which sent every invite text free until
     * decision 2900 — and neither waited for 10DLC to do it, which was the stated
     * reason the case was supposedly unreachable. **The stale half that mattered
     * was the excuse rather than the fact**: an enum case documented as
     * unreachable *for a named external reason* invites the next reader to skip
     * it, and this is the case that moves every tenant's balance.
     */
    case Consume = 'consume';

    /**
     * Credits given rather than sold — the monthly allotment.
     *
     * **500 SMS per account per month** (2060, restated unchanged at 3298), always
     * written to {@see CreditPool::Monthly} and enforced there by a CHECK as well
     * as by {@see self::poolWhenCredited()}.
     *
     * ⚠️ **IT HAS AN EXPIRY, WHICH IS THE HALF THAT IS EASY TO LOSE.** 3307 makes
     * the allotment reset at the period boundary, and an append-only ledger
     * expires a grant by writing against it — {@see self::Expire}. A `Grant`
     * written with no matching expiry accumulates, and a year later the "monthly"
     * pool is six thousand credits nobody was ever meant to have.
     *
     * ⚠️ **WHO MAY RECEIVE ONE IS NOT THIS ENUM'S QUESTION AND IS NOT MERELY
     * "EVERYONE"** (2066, 3117). A fourteen-day no-card trial that mints 500 real,
     * billable SMS is a fraud surface; `App\Services\Billing\TrialEligibility` is
     * the control that was built ahead of this and
     * `App\Console\Commands\ResetMonthlyCredits` is where it is asked.
     */
    case Grant = 'grant';

    /**
     * The monthly allotment lapsing at the period boundary (3307).
     *
     * ⚠️ **AN EXPIRY IS A MOVEMENT, NOT A DELETION, AND THAT IS THE WHOLE REASON
     * THIS CASE EXISTS RATHER THAN A `DELETE`.** `credit_ledger` is append-only —
     * the model throws on `updating()` and `deleting()` — so a grant is expired by
     * a negative row that takes {@see CreditPool::Monthly} to zero. What survives
     * is the difference between a tenant who used their allotment and one who let
     * it lapse, which is a support conversation and a churn signal, and which a
     * deletion would erase.
     *
     * ⚠️ **NOT `Adjust`, THOUGH IT WOULD HAVE FITTED.** `Adjust` is *"an operator
     * correction, in either direction"* and requires a typed reason for exactly
     * that purpose. A scheduled expiry has no operator and no judgement in it; if
     * it wore `Adjust`'s clothes then every genuine operator correction would be
     * buried under one automatic row per tenant per month, and the column that
     * tells the two apart would have stopped doing so.
     *
     * Always decreases the balance, and only ever from the monthly pool.
     */
    case Expire = 'expire';

    /**
     * Credits moved after a reversed payment.
     *
     * Deliberately unconstrained in sign: a refund can mean credits handed back
     * to a tenant, or credits withdrawn because the money went back to them.
     * Which one belongs to the purchase flow that writes it, and guessing here
     * would put a CHECK in the database enforcing a decision nobody made.
     */
    case Refund = 'refund';

    /**
     * An operator correction, in either direction.
     *
     * The escape hatch every ledger needs, and the one whose rows have to be
     * readable a year later — which is why a reason is required for this kind at
     * the database rather than by convention.
     */
    case Adjust = 'adjust';

    /**
     * Whether this kind may only ever increase the balance.
     *
     * A match rather than a comparison so that a sixth case cannot inherit an
     * answer nobody chose — `OptOutScope::requiresBusiness()`'s reasoning.
     */
    public function isAlwaysCredit(): bool
    {
        return match ($this) {
            self::Purchase, self::Grant => true,
            self::Consume, self::Expire, self::Refund, self::Adjust => false,
        };
    }

    /**
     * Whether this kind may only ever decrease the balance.
     */
    public function isAlwaysDebit(): bool
    {
        return match ($this) {
            self::Consume, self::Expire => true,
            self::Purchase, self::Grant, self::Refund, self::Adjust => false,
        };
    }

    /**
     * Whether a row of this kind must carry a human-readable reason.
     *
     * A purchase explains itself through `ref_type`/`ref_id` pointing at the
     * payment it came from; an adjustment has no such referent, so the only
     * record of why the balance moved is the sentence somebody typed.
     */
    public function requiresReason(): bool
    {
        return match ($this) {
            self::Adjust => true,
            self::Purchase, self::Consume, self::Grant, self::Expire, self::Refund => false,
        };
    }

    /**
     * The pool a movement of this kind lands in when it *increases* the balance.
     *
     * The two asymmetries here are the load-bearing part:
     *
     * `Grant` is the only kind that funds {@see CreditPool::Monthly}, so the
     * allotment is the only thing that can expire. A CHECK constraint says the
     * same thing at the database, on decision 216's two-layer reasoning.
     *
     * `Adjust` funds {@see CreditPool::TopUp} even though a negative `Adjust`
     * draws monthly-first ({@see self::drawOrder()}). It is deliberate and it is
     * the direction that cannot hurt anybody: a support operator's goodwill
     * credits land where they will still be there next month. Landing them in the
     * monthly pool would quietly delete them at the period boundary, and the
     * tenant would be told they had been credited.
     *
     * ⚠️ `Consume` and `Expire` are always debits ({@see self::isAlwaysDebit()}),
     * so neither is reachable through this method — they are named rather than
     * defaulted so that a seventh kind is a compile-time conversation.
     */
    public function poolWhenCredited(): CreditPool
    {
        return match ($this) {
            self::Grant => CreditPool::Monthly,
            self::Purchase, self::Refund, self::Adjust, self::Consume, self::Expire => CreditPool::TopUp,
        };
    }

    /**
     * The pools a movement of this kind may draw from, in the order it draws.
     *
     * ⚠️ **`Refund` DRAWS FROM TOP-UP ONLY, AND IT IS THE ONE THAT LOOKS WRONG.**
     * A negative refund is *"credits withdrawn because the money went back to
     * them"* — it reverses a purchase, and a purchase is always top-up. Letting it
     * spill into the monthly pool would take back an allotment nobody paid for to
     * settle a refund of money somebody did.
     *
     * ⚠️ **`Expire` DRAWS FROM MONTHLY ONLY**, which is what makes 3307's *"top up
     * don't expire"* true rather than merely intended. If this arm ever spilled,
     * the reset would eat paid credits at the end of every month.
     *
     * `Purchase` and `Grant` are always credits and are never drawn from; each
     * names its own pool rather than defaulting, for `poolWhenCredited()`'s
     * reason.
     *
     * @return non-empty-list<CreditPool>
     */
    public function drawOrder(): array
    {
        return match ($this) {
            self::Consume, self::Adjust => CreditPool::drawOrder(),
            self::Expire, self::Grant => [CreditPool::Monthly],
            self::Refund, self::Purchase => [CreditPool::TopUp],
        };
    }
}

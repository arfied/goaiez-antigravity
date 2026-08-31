<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which of the two credit pools a `credit_ledger` row belongs to (decision 3307).
 *
 * The owner's ruling, verbatim in the parts that matter: *"top up don't expire
 * they stay in the account on month CREdits reset each month"*, and *"keep the
 * credit pool sperate … monthly runs out it takes from the top up pools."* Three
 * facts come out of that and all three live here:
 *
 *   1. **Two pools, one balance.** A tenant has a single number of credits and it
 *      is made of two parts with different lifetimes.
 *   2. **The lifetimes differ.** {@see self::Monthly} is granted at the start of a
 *      period and expires at the end of it; {@see self::TopUp} was paid for and
 *      never expires.
 *   3. **The draw order is monthly first** ({@see self::drawOrder()}), so the part
 *      that is about to expire is spent before the part that never will.
 *
 * ⚠️ **A STRING COLUMN CAST TO THIS ENUM, NEVER A POSTGRES ENUM TYPE** —
 * `CLAUDE.md`'s standing rule and decision 863's precedent on `credit_ledger.kind`
 * one column over.
 *
 * ⚠️ **THIS IS NOT A SECOND BALANCE STORE, IT IS A DISCRIMINATOR.** Decision 286's
 * lesson is that one number held in two places is the number that loses; the
 * balance is still `balance_after` on the head row, only now the head row is
 * per-pool. `App\Services\Billing\CreditLedger` is still the only reader and the
 * only writer, and a screen that computes a pool balance for itself is the wrong
 * answer this arrangement makes *more* attractive rather than less.
 *
 * ⚠️ **AND IT IS NOT A PRODUCT DIMENSION.** `credit_ledger` counts SMS credits and
 * nothing else — ⛔ **and what a send COSTS is deliberately no longer written
 * here (12487).** This cell has carried the retail figure twice and been wrong
 * twice: *"one credit per send (T137 R9, decision 2144)"* until 9182, then *"one
 * for the text and one for the media"* until 12461. **`SmsCreditUnits` is the
 * arithmetic and `SendCredits` is where it is applied**; this enum's subject is
 * that a pool is not a product. The email allotment (1,000/month,
 * 3298) and the AI allotment (money, in hundredths of a cent) are different units
 * in different books, and putting them in this column would make a send and an
 * email interchangeable at the balance.
 *
 * ⛔ **"WHOSE *DENOMINATION* IS STILL OPEN AT 3304" WAS TRUE FOR ONE DAY AND WAS
 * FALSE FOR TEN.** 3412 answered 3304 on 2026-08-14 — retail credit, not provider
 * spend — and 9180 moved the amount on 2026-08-24 without re-opening the
 * denomination. **The figure is the seed's to state and is deliberately not
 * repeated here**, because a monthly allotment written into a docblock about a
 * pool dimension is an inventory where a property was wanted (8861).
 *
 * ⚠️ **BOTH HALVES OF THIS PARAGRAPH WERE CORRECTED IN ONE WAVE BY TWO LANES THAT
 * COULD NOT SEE EACH OTHER, AND EACH LANE'S VERSION RE-ASSERTED THE OTHER'S
 * DEFECT** — wave 24, resolved by the integrator. 9224–9227 fixed the credits
 * sentence and kept *"$30/month, whose denomination is still open at 3304"*;
 * 9220–9223 fixed the denomination sentence and kept *"one credit per send"*.
 * **Neither branch was wrong on its own and the merge of either alone would have
 * shipped a false sentence**, which is why the conflict was reconstructed rather
 * than resolved to a side.
 */
enum CreditPool: string
{
    /**
     * The allotment granted each period and expired at the end of it.
     *
     * 500 SMS per account per month (2060, confirmed unchanged at 3298), one
     * shared balance across review invites, missed-call text-back and the chat
     * bot (2066) — nothing in the reply splits it per feature.
     *
     * ⚠️ **IT EXPIRES BY BEING WRITTEN AGAINST, NEVER BY BEING DELETED** (3307).
     * `credit_ledger` is append-only and the model throws on `updating()` and
     * `deleting()`, so the reset writes a `CreditKind::Expire` movement that takes
     * this pool to zero and then a `CreditKind::Grant` that refills it. The
     * history of what a tenant was given and what they let lapse survives, which
     * is the whole reason 286 chose a ledger over two integers on a row.
     *
     * ⛔ **NOTHING MAY BROADCAST FROM HERE** (3309). *"monthly sms credits can not
     * be used for this."*
     */
    case Monthly = 'monthly';

    /**
     * Credits that were paid for. They never expire.
     *
     * $50 buys 1,000 automatically, $250 buys 10,000 through an operator (2061,
     * confirmed unchanged at 3301). This is the pool `credit_ledger` has held
     * since it was built — every row that existed before decision 3307 is one of
     * these, which is why the migration backfills to this case.
     *
     * ⚠️ **THE ONLY POOL SMS BROADCASTING MAY SPEND** (3309, 3310), and that is
     * one of only two clauses of the old two-pool rule to survive: *"they need to
     * buy credit and finish 10dlc on there number."*
     */
    case TopUp = 'topup';

    /**
     * The order a spend consumes the pools in — 3307's spill-over, in one place.
     *
     * Monthly first, because it is the part with an expiry date: spending the
     * durable pool while a grant sat unused would silently convert money the
     * tenant paid into an allotment they lost at the period boundary.
     *
     * ⚠️ **THIS IS ALSO WHAT DROPS THE OLD RULE'S FIRST CLAUSE.** 152 quoted a
     * two-pool rule whose first half was *"automation can never spend purchased
     * credits"*; 3114 found that citation unresolvable and 3309 answered it — a
     * review invite that exhausts the monthly pool draws on top-up like anything
     * else. The *second* clause survives and is stricter than it was, and it is
     * expressed by refusing the monthly pool at the caller rather than by
     * reordering this list.
     *
     * @return non-empty-list<self>
     */
    public static function drawOrder(): array
    {
        return [self::Monthly, self::TopUp];
    }

    /**
     * Whether this pool is emptied at the period boundary.
     *
     * A match rather than a comparison so that a third pool cannot inherit an
     * answer nobody chose — `CreditKind::isAlwaysCredit()`'s reasoning, and the
     * answer here decides whether a tenant's money can evaporate.
     */
    public function expiresAtPeriodBoundary(): bool
    {
        return match ($this) {
            self::Monthly => true,
            self::TopUp => false,
        };
    }
}

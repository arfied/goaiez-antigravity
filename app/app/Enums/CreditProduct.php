<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which of the three allotments a `credit_ledger` row belongs to (decision 3419).
 *
 * 3298, the owner's model, grants three separate things every month **per
 * account**: 500 SMS, 1,000 emails and $50 of AI credit (9180, reversing 3412's
 * $30 on 2026-08-24). 3307 splits each of
 * those into a monthly pool that resets at the period boundary and a top-up pool
 * that never expires. **Three products × two pools = six balances**, and this is
 * the dimension the table was missing — before it, an email spend and a text
 * spend were the same row and an AI balance could not exist at all.
 *
 * ⚠️ **ONE LEDGER, NOT THREE, AND THE ALTERNATIVE WAS CONSIDERED.** A separate AI
 * book was the obvious answer to the unit problem below; it was rejected on
 * 2026-08-14 because one balance per tenant beats two books to reconcile, and
 * because `credit_ledger` already carries the append-only guarantees, the
 * per-movement lock, the RLS policy and the chokepoint lint that a second table
 * would have to grow from scratch.
 *
 * ⛔ **THE PRODUCTS ARE NOT COUNTED IN THE SAME UNIT AND THAT IS THE HAZARD.**
 * SMS and email are whole sends; AI is money at hundredths of a cent. So the unit
 * is a property of the product ({@see self::unit()}) rather than something a
 * caller remembers, and the registry seed's own denomination is stated beside the
 * key it comes from ({@see self::monthlyGrantKey()}) so that the key and the
 * conversion are chosen together and cannot drift apart.
 *
 * ⛔ **THERE IS DELIBERATELY NO "TOTAL BALANCE" ANYWHERE.** Adding 500 sends to
 * 300,000 hundredths of a cent produces a number, and the number is meaningless.
 * `App\Services\Billing\CreditLedger::balance()` therefore **requires** a product
 * — the parameter is not optional and must not be given a default, which is what
 * makes the nonsense unwritable rather than merely discouraged.
 *
 * ⚠️ **A STRING COLUMN CAST TO THIS ENUM, NEVER A POSTGRES ENUM TYPE** —
 * `CLAUDE.md`'s standing rule, and decision 863's precedent one column over on
 * `credit_ledger.kind`.
 */
enum CreditProduct: string
{
    /**
     * Text messages. **One credit for the text and one for the media** — 9182,
     * confirmed 9193, reversing T137 R9's *"1 credit per send"* (2144). A plain
     * text is one; a text carrying a picture is two, and
     * `App\Services\Billing\SendCredits` is the one place that is decided.
     *
     * 500 a month per account (3298, confirming 2060), one shared balance across
     * review invites, missed-call text-back and the chat bot (2066).
     *
     * ⛔ **SMS BROADCASTING MAY NEVER SPEND THIS PRODUCT'S MONTHLY POOL** (3309),
     * and needs the tenant's own brand and number besides (3310). See
     * `App\Services\Campaigns\BroadcastPreconditions`.
     *
     * ⚠️ **EVERY ROW THAT EXISTED BEFORE THIS ENUM IS ONE OF THESE.** The table
     * counted whole SMS sends and nothing else (3357), so the migration's column
     * default restates a fact rather than choosing one.
     */
    case Sms = 'sms';

    /**
     * Platform emails. One unit per send, on `App\Services\Billing\EmailCredits`.
     *
     * 1,000 a month per account (3298), metered at $20 per 1,000 (3299).
     *
     * ⚠️ **THIS IS THE POOL 2902's OBJECTION ASKED FOR AND `EmailCredits` COULD
     * NOT REACH.** That class debited the one undifferentiated balance and said so
     * in as many words — *"the pool it happens against is the single
     * undifferentiated balance `CreditLedger` keeps today … the one place that
     * changes when the ledger learns which product a unit belongs to."* This is
     * that change; an email no longer spends a text.
     */
    case Email = 'email';

    /**
     * AI credit. **Money, in hundredths of a cent**, not a count of anything.
     *
     * $50 of *retail* credit a month, costing us about $6.25 at the 8:1 sell rate
     * (9180 for the figure, 3412 for what a dollar of it means — the markup is
     * unchanged and only the numerator moved). The debit is
     * `ai_calls.retail_hundredths_cents`, written by `App\Services\Ai\AiSpend` at
     * call time.
     *
     * ⛔ **THE GRANT SEED IS IN CENTS AND THIS POOL IS IN HUNDREDTHS.** See
     * {@see self::ledgerUnitsFromGrant()} and {@see CreditUnit}. Reading `5000`
     * straight in grants a hundredth of what was promised, and every screen goes
     * on looking right (3331).
     */
    case Ai = 'ai';

    /**
     * What one unit of this product counts.
     *
     * A `match` rather than a comparison so that a fourth product is a
     * compile-time conversation — `CreditKind::isAlwaysCredit()`'s reasoning, and
     * here the answer decides whether a figure is money or a message.
     */
    public function unit(): CreditUnit
    {
        return match ($this) {
            self::Sms, self::Email => CreditUnit::Send,
            self::Ai => CreditUnit::HundredthsOfACent,
        };
    }

    /**
     * The `plan_entitlements` key holding this product's monthly allotment.
     *
     * ⚠️ **A PER-PLAN ENTITLEMENT AND NOT A PLATFORM SETTING**, which is 3320's
     * ruling: what a plan grants is what `plan_entitlements` versions, so a tenant
     * who signed up under a 500-message allotment keeps it when the platform
     * default moves (507's grandfathering applied to credits). Read it through
     * `DefaultsRegistry::entitlement()`, **never** `int()` — see
     * `ResetMonthlyCredits`, where reading it as a platform setting is exactly the
     * defect 3421 records.
     *
     * ⚠️ **THE `_cents` SUFFIX ON THE AI KEY IS LOAD-BEARING AND IS PINNED BY A
     * TEST.** It is the only thing in the key names that says the AI seed is
     * denominated differently from the other two, and `CreditProductsTest` asserts
     * that a key ends in `_cents` if and only if this product's unit is money —
     * so renaming one without the other reddens the build instead of silently
     * re-opening 3331.
     */
    public function monthlyGrantKey(): string
    {
        return match ($this) {
            self::Sms => 'credits.monthly_grant.sms',
            self::Email => 'credits.monthly_grant.emails',
            self::Ai => 'credits.monthly_grant.ai_cents',
        };
    }

    /**
     * This product's monthly allotment seed, converted into ledger units.
     *
     * ⛔ **THE ONE PLACE A REGISTRY FIGURE BECOMES A LEDGER BALANCE** (3420). For
     * SMS and email the seed already *is* the ledger unit — 500 sends is 500
     * units, and multiplying would be as wrong as not multiplying. For AI the seed
     * is integer cents and the pool is hundredths, so it goes through
     * {@see CreditUnit::fromCents()}.
     *
     * ⚠️ **IT TAKES THE SEED AND RETURNS UNITS, RATHER THAN LETTING A CALLER SEE
     * BOTH.** `ResetMonthlyCredits` reads the entitlement and hands it straight
     * here; nothing between the registry and the ledger ever holds an AI figure it
     * could accidentally use undenominated. That is the design 3331 asks for —
     * *"pin the conversion in ONE place"* — expressed as a shape rather than as an
     * instruction.
     *
     * @param  int  $seed  The figure as the registry states it: sends for
     *                     {@see self::Sms} and {@see self::Email}, integer **cents**
     *                     for {@see self::Ai}.
     * @return int The same allotment in this product's ledger units.
     */
    public function ledgerUnitsFromGrant(int $seed): int
    {
        return match ($this->unit()) {
            CreditUnit::Send => $seed,
            CreditUnit::HundredthsOfACent => $this->unit()->fromCents($seed),
        };
    }

    /**
     * The `platform_settings` key holding what one top-up of this product costs
     * at this tier, in integer cents (decisions 3301–3303).
     *
     * ⚠️ **THE PRICE IS THE FIGURE A CARD IS CHARGED, SO IT IS CENTS ON ALL THREE
     * PRODUCTS** — unlike the grant beside it, which is a count on two of them.
     * That asymmetry is the SKUs' own and is why these are two methods rather than
     * one with a suffix.
     */
    public function topUpPriceKey(CreditTopUpTier $tier): string
    {
        return 'credits.topup.'.$this->skuFragment().'.'.$tier->keyFragment().'_price_cents';
    }

    /**
     * The `platform_settings` key holding what one top-up of this product *grants*
     * at this tier, **in the seed's own denomination** (decisions 3301–3303).
     *
     * ⛔ **THE DENOMINATION IS NOT THE SAME ACROSS THE THREE AND THAT IS 3331's
     * HAZARD ARRIVING ON THE PAID SIDE.** SMS and email state a count of sends
     * (`automatic_messages`, `automatic_emails`); **AI states integer cents**
     * (`automatic_grant_cents`), because 3303's discount is expressed as granted
     * money rather than as a better unit rate. Reading the AI figure into the
     * ledger without {@see self::ledgerUnitsFromGrant()} credits a hundredth of
     * what was paid for, and the balance looks entirely plausible afterwards.
     *
     * ⚠️ **THE `_cents` SUFFIX IS THE ONLY WARNING IN THE KEY NAME AND IT IS
     * PINNED BY A TEST**, exactly as {@see self::monthlyGrantKey()}'s is: a key
     * ends in `_cents` if and only if this product's unit is money.
     */
    public function topUpGrantKey(CreditTopUpTier $tier): string
    {
        $suffix = match ($this) {
            self::Sms => 'messages',
            self::Email => 'emails',
            self::Ai => 'grant_cents',
        };

        return 'credits.topup.'.$this->skuFragment().'.'.$tier->keyFragment().'_'.$suffix;
    }

    /**
     * The `platform_settings` key holding the balance at or below which automatic
     * top-up fires for this product — **in this product's own ledger unit**.
     *
     * ⛔ **THE UNIT DIFFERS AND THE KEY NAME DOES NOT WARN YOU**, unlike
     * {@see self::topUpGrantKey()}'s `_cents` suffix. SMS and email thresholds
     * count whole sends; the AI one counts **hundredths of a cent**, so its seed
     * of `5_000` is fifty cents of credit and not fifty dollars. The suffix is
     * absent deliberately: a `_cents` ending would be *wrong* for AI here, since
     * the figure is compared against a ledger balance rather than against a
     * price, and inventing a `_hundredths` suffix for one of three keys reads as
     * an exception rather than as the rule that every threshold is in ledger
     * units.
     *
     * ⚠️ **COMPARED AGAINST THE TOTAL SPENDABLE BALANCE, NOT THE TOP-UP POOL.** A
     * spend draws the monthly grant first and spills into top-up (3307), so an
     * empty top-up pool is the ordinary state of an account inside its allowance.
     */
    public function autoTopUpThresholdKey(): string
    {
        return 'credits.auto_topup.threshold.'.$this->skuFragment();
    }

    /**
     * How the SKU keys spell this product.
     *
     * ⚠️ **NOT `$this->value`, THOUGH IT IS EQUAL TO IT TODAY.** The stored column
     * value and the registry key fragment are two different contracts that happen
     * to agree, and writing the agreement as a `match` is what stops a rename of
     * the *column* value silently repointing every price lookup at a key that does
     * not exist — which `DefaultsRegistry` answers with an exception naming a typo
     * rather than a missing SKU.
     */
    private function skuFragment(): string
    {
        return match ($this) {
            self::Sms => 'sms',
            self::Email => 'email',
            self::Ai => 'ai',
        };
    }
}

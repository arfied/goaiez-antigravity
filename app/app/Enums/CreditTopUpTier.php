<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which of a product's two top-up SKUs a purchase is buying (decisions
 * 3301–3303).
 *
 * The owner's price list has exactly two tiers per product and the second is
 * cheaper per unit than the first on every one of them:
 *
 *   SMS     $50 → 1,000 (5.00¢) · $250 → 10,000 (2.50¢)
 *   email   $50 → 2,500 (2.00¢) · $150 → 15,000 (1.00¢)
 *   AI      $50 → $50 of credit · **$200 → $300 of credit**
 *
 * ⚠️ **THE NAMES ARE THE OWNER'S AND THEY DESCRIBE A *ROUTE*, NOT A SIZE.**
 * "Automatic" is the increment an automatic top-up arrangement would charge
 * (3306) and "manual" is the larger package an operator or an owner buys
 * deliberately. Nothing here is automatic today — the arrangement is unbuilt —
 * so **both tiers are bought the same way, by a person who confirmed the
 * amount**, and the tier only selects which pair of registry keys is read.
 *
 * ⛔ **THE HALF-PRICE BREAK IS REAL AND IS NOT TO BE "CORRECTED" TOWARD ONE
 * RATE** (3302). A tenant who buys the manual package pays half what the
 * automatic tier charges for the same send, on both metered products, and an
 * edit that removed the inconsistency would read on a diff as a tidy-up.
 *
 * ⚠️ **AI IS THE ODD ONE AND ITS ODDNESS IS DELIBERATE** (3303). It is the only
 * SKU whose discount is expressed as *granted credit* rather than as a better
 * unit rate — $200 paid, $300 granted, "so get $100 free" — which is why
 * {@see CreditProduct::topUpGrantKey()} answers with a `_cents` key for AI and a
 * count for the other two.
 *
 * A string cast, never a database enum type — `CLAUDE.md`'s standing rule and
 * decision 863's precedent on `credit_ledger.kind`.
 */
enum CreditTopUpTier: string
{
    /**
     * The smaller pack: $50 on all three products (3301–3303).
     *
     * Named for the arrangement that would buy it without a person present
     * (3306, `credits.auto_topup.*`). ⚠️ **That arrangement does not exist**, so
     * every purchase of this tier today is one somebody confirmed.
     */
    case Automatic = 'automatic';

    /**
     * The larger package, at a better rate on every product (3301–3303).
     */
    case Manual = 'manual';

    /**
     * The `credits.topup.*` key fragment this tier contributes.
     *
     * A `match` rather than `$this->value` so that a third tier is a
     * compile-time conversation — `CreditKind::isAlwaysCredit()`'s reasoning.
     * The two happen to be identical to the case values today, and writing that
     * as an identity rather than as an accident is what stops a rename of the
     * stored value silently repointing the registry read.
     */
    public function keyFragment(): string
    {
        return match ($this) {
            self::Automatic => 'automatic',
            self::Manual => 'manual',
        };
    }
}

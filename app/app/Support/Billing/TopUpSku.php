<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Enums\CreditProduct;
use App\Enums\CreditTopUpTier;
use App\Support\Money;

/**
 * One line of the owner's top-up price list, resolved: what it costs and what it
 * puts in the pool (decisions 3301–3303).
 *
 * ⛔ **THE PRICE AND THE UNITS ARE BOTH READ FROM THE REGISTRY AND NEITHER IS
 * DERIVED FROM THE OTHER.** They are two seeded figures that must agree, which is
 * normally the trap — and here it is the point, exactly as 3324 argues for the
 * metered email rate: the owner stated the price and the quantity together, the
 * agreement is the evidence, and a derivation would recompute one from a wrong
 * other and stay green. {@see App\Services\Billing\TopUpCatalog} is the one place
 * that reads them.
 *
 * ⛔ **`$units` IS ALREADY IN LEDGER UNITS AND THAT IS THE ONLY REASON THIS TYPE
 * EXISTS RATHER THAN A PAIR OF INTEGERS.** The registry states the AI grant in
 * **cents** and the AI pool counts **hundredths of a cent** (3331, 3420), so
 * something has to convert, once, and be the only thing that does. The conversion
 * happens in {@see CreditProduct::ledgerUnitsFromGrant()} before this object is
 * built, so **nothing downstream ever holds an undenominated AI figure it could
 * accidentally use**. A caller reaching for `$sku->units` is holding the number
 * `CreditLedger::record()` wants, always, for all three products.
 */
final readonly class TopUpSku
{
    /**
     * @param  Money  $price  What the card is charged. Integer cents plus a
     *                        currency, `18` §Money handling.
     * @param  int  $units  What lands in {@see App\Enums\CreditPool::TopUp}, in
     *                      `$product`'s own ledger units — whole sends for SMS and
     *                      email, hundredths of a cent for AI.
     * @param  int  $grantSeed  The figure exactly as the registry states it, kept
     *                          only so a mismatch can be reported in the
     *                          denomination somebody typed. ⚠️ **Never spend this;
     *                          spend `$units`.**
     */
    public function __construct(
        public CreditProduct $product,
        public CreditTopUpTier $tier,
        public Money $price,
        public int $units,
        public int $grantSeed,
    ) {}

    /**
     * The SKU, as one string, for a vendor description and an audit entry.
     *
     * ⚠️ **IT NAMES THE PRODUCT AND THE TIER AND NOT THE PRICE.** This string
     * reaches Authorize.Net's `order.description` and a Stripe line item name,
     * both of which a customer reads on a receipt; the price is on the same
     * receipt as a number, and putting it in the label as well is a second copy
     * that can disagree after a price change (512's shape).
     */
    public function label(): string
    {
        return $this->product->value.':'.$this->tier->value;
    }
}

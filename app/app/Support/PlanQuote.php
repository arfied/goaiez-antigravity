<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A selection and the two rates it was priced at, resolved once (decision 4346).
 *
 * ## ⛔ WHY THIS EXISTS: TWO `PlanCharges` INSTANCES PRICED ONE PURCHASE
 *
 * `AuthorizeNetGateway` and `Subscriptions` each construct their own
 * `PlanCharges`, each with its own per-instance memo of the live offers, and no
 * container binding shares them. So `subscribe()` priced the ARB call from one
 * instance and then — **after the network round trip** — wrote the agreed-price
 * columns from the other. A founder window closing in that gap created a
 * subscription charged at the founder rate with a row stating the retail one,
 * for ever, and `RenewalReminders` quotes that row in a statutory notice. That is
 * 3444's defect reached through the memo that was added to prevent it, and the
 * same shape sits on the Stripe path between `sessionParams()` and
 * `recordQuotedSelection()`.
 *
 * ⚠️ **THE FIX IS TO CARRY THE NUMBERS, NOT TO SHARE THE OBJECT.** Binding
 * `PlanCharges` as a scoped singleton would have made the memo per-request
 * instead of per-instance — which reads as tidier and quietly changes what two
 * deliberate tests are testing, because "a fresh instance sees the close" stops
 * being expressible. A quote is passed from the caller that resolved it to the
 * writer that stores it, so the price cannot be looked up twice at all.
 *
 * ⚠️ **IT CARRIES MONEY AND {@see PlanSelection} DELIBERATELY DOES NOT.** That
 * class refuses to hold a price because a selection is built from request input
 * and a `Money` on it would be a price posted by a form. This is the opposite
 * object: it is only ever built by `PlanCharges::quote()`, from the registry or
 * a live offer, and never from anything a request carries.
 */
final readonly class PlanQuote
{
    public function __construct(
        public PlanSelection $selection,
        public Money $unitPrice,
        public Money $additionalLocationPrice,
    ) {}

    /**
     * The whole price: the base plan plus each extra location.
     *
     * ⚠️ **THE ONLY PLACE THAT SUM IS WRITTEN.** `PlanCharges::priceFor()`
     * delegates here rather than repeating it, so there is no second arithmetic
     * to drift — which matters more than it looks, because the two would agree
     * for one location and disagree for four.
     */
    public function total(): Money
    {
        if ($this->selection->additionalLocations === 0) {
            return $this->unitPrice;
        }

        return $this->unitPrice->plus(
            $this->additionalLocationPrice->times($this->selection->additionalLocations)
        );
    }
}

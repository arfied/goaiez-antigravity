<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\CreditProduct;
use App\Enums\CreditTopUpTier;
use App\Exceptions\CreditPurchaseRefused;
use App\Services\Config\DefaultsRegistry;
use App\Support\Billing\TopUpSku;
use App\Support\Money;

/**
 * The owner's top-up price list, read from the registry and never restated
 * (decisions 3301–3303, 3318).
 *
 * ⛔ **NOT ONE FIGURE IN THIS FILE.** Six prices and six quantities were seeded on
 * 2026-08-13 with a decision reference on every one, and 3329 recorded the cost of
 * their having no reader: *"until the ledger grows a pool and a product dimension
 * and the metering reads the rate, every figure here is a row in a table, and a
 * row in a table is not a ceiling."* This is that reader for the twelve SKU keys.
 * A price typed here would be a second source for a number that has already moved
 * by a factor of ten once (3299).
 *
 * ⛔ **AND THE CONVERSION HAPPENS HERE, ONCE.** The registry states the AI grant
 * in **cents** and the AI pool counts **hundredths of a cent**; every SMS and
 * email grant is already a count of sends. {@see CreditProduct::ledgerUnitsFromGrant()}
 * is the single boundary and this is the only caller of it on the paid side, so a
 * {@see TopUpSku} always leaves here holding ledger units. 3331 predicted this
 * exact defect by name — *"a lane that reads `credits.topup.ai.manual_grant_cents`
 * and writes it straight into a hundredths-denominated balance under-grants by a
 * factor of a hundred, and the tenant's balance would look plausible the whole
 * time."*
 *
 * ## What it refuses, and why refusing is the whole job
 *
 * ⚠️ **A ZERO OR NEGATIVE PRICE, AND A ZERO OR NEGATIVE GRANT, ARE BOTH
 * REFUSED.** Every key here is admin-editable (3415), so both are one Ops typo
 * away — and each fails in a way nothing else would notice. A `0` price charges
 * nobody and grants a pack; a `0` grant charges $50 and credits nothing, with a
 * receipt, a ledger that balances and a balance that never moved.
 *
 * ⚠️ **THE PRICE IS NOT COMPARED AGAINST THE OTHER TIER HERE.** `RegistryTest`
 * already pins the half-price break on both metered products and the direction of
 * the AI manual load (3325), which is the right place for a relationship between
 * two seeds. Repeating it at read time would be a second definition of a rule that
 * already fails the build.
 */
final class TopUpCatalog
{
    public function __construct(
        private readonly DefaultsRegistry $registry = new DefaultsRegistry,
    ) {}

    /**
     * One line of the price list, priced and converted.
     *
     * @throws CreditPurchaseRefused when either seeded figure is not a usable
     *                               number — an Ops edit away on both.
     */
    public function sku(CreditProduct $product, CreditTopUpTier $tier): TopUpSku
    {
        $priceCents = $this->registry->int($product->topUpPriceKey($tier));
        $grantSeed = $this->registry->int($product->topUpGrantKey($tier));

        if ($priceCents <= 0) {
            throw CreditPurchaseRefused::because(
                "The {$product->value} {$tier->value} top-up is priced at {$priceCents} cents, "
                .'which is not a price. A zero-priced pack charges nobody and grants '
                .'credit, and every screen selling it looks right.'
            );
        }

        if ($grantSeed <= 0) {
            throw CreditPurchaseRefused::because(
                "The {$product->value} {$tier->value} top-up grants {$grantSeed}, which is "
                .'not a pack. A pack that grants nothing still charges the card, and the '
                .'ledger balances afterwards.'
            );
        }

        return new TopUpSku(
            product: $product,
            tier: $tier,
            price: Money::of($priceCents, $this->currency()),

            // ⛔ THE 100× BOUNDARY. Sends stay sends; cents become hundredths.
            // Nothing between this line and `CreditLedger::record()` holds the
            // seed, which is what makes the factor unwritable a second time.
            units: $product->ledgerUnitsFromGrant($grantSeed),
            grantSeed: $grantSeed,
        );
    }

    /**
     * Every SKU, for a screen and for the tests that walk them.
     *
     * ⚠️ **DERIVED FROM THE ENUM CASES RATHER THAN LISTED**, so a fourth product
     * or a third tier arrives here as a registry key that does not exist — an
     * exception naming the missing key — rather than as a SKU quietly missing from
     * a list nobody re-reads (256's shape).
     *
     * @return list<TopUpSku>
     */
    public function all(): array
    {
        $skus = [];

        foreach (CreditProduct::cases() as $product) {
            foreach (CreditTopUpTier::cases() as $tier) {
                $skus[] = $this->sku($product, $tier);
            }
        }

        return $skus;
    }

    /**
     * ⚠️ The same key `PlanCharges` reads, and it fails rather than falling back:
     * a price with no currency is not a price (`18` §Money handling).
     */
    private function currency(): string
    {
        $currency = $this->registry->value('billing.currency');

        if (! is_string($currency) || $currency === '') {
            throw CreditPurchaseRefused::because(
                'billing.currency must be an ISO 4217 code; a top-up cannot be charged '
                .'without one.'
            );
        }

        return $currency;
    }
}

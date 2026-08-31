<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\BillingTerm;
use App\Services\Billing\PlanCharges;
use InvalidArgumentException;

/**
 * What somebody is buying: a term, whether it is paid in instalments, and how
 * many locations beyond the first (decision 2680).
 *
 * ⚠️ **ONE VALUE RATHER THAN THREE PARAMETERS, BECAUSE THE THREE ARE NOT
 * INDEPENDENT.** "Monthly, in three instalments" is not a thing this product
 * sells — 2055 gives the instalment option to the *annual* price and to nothing
 * else — and a method taking `(BillingTerm $term, bool $instalments, int
 * $locations)` is a method that accepts that combination and leaves each caller
 * to refuse it. The refusal lives here, once, and the named constructors below
 * mean a caller cannot even spell the invalid case.
 *
 * ⚠️ **IT CARRIES NO MONEY.** Prices come from the registry at the moment of
 * purchase ({@see PlanCharges}), because decision 689 keeps
 * the registry the only place a price is written and 2055 keeps the instalment
 * figures derived rather than stored.
 *
 * ⛔ **BOTH CITATIONS IN THIS FILE NAMED `App\Services\Billing\AnnualPlan`, A
 * CLASS THAT HAS NEVER EXISTED — CORRECTED 2026-08-29** (12333). One of them
 * was written out **fully qualified**, which is the spelling a reader trusts
 * most and the one an editor jumps to, and it resolved to nothing. **Neither
 * argument moves**: the registry is still the only place a price is written and
 * the instalment count is still derived. A `Money` on this object would be a price
 * captured in a request and carried to a vendor call — the shape that lets a
 * form post its own price.
 */
final readonly class PlanSelection
{
    /**
     * @param  int  $additionalLocations  Locations beyond the one the base price
     *                                    includes. Never negative.
     */
    private function __construct(
        public BillingTerm $term,
        public bool $inInstalments,
        public int $additionalLocations,
    ) {}

    /**
     * The monthly plan — what every existing caller buys, and the default.
     */
    public static function monthly(int $additionalLocations = 0): self
    {
        return new self(BillingTerm::Monthly, false, self::locations($additionalLocations));
    }

    /**
     * The annual plan, paid in one charge.
     */
    public static function annual(int $additionalLocations = 0): self
    {
        return new self(BillingTerm::Annual, false, self::locations($additionalLocations));
    }

    /**
     * The annual plan, collected in instalments.
     *
     * ⚠️ **THE COUNT IS NOT A CONSTANT OF THIS PRODUCT AND IS DELIBERATELY NOT
     * NAMED HERE.** {@see PlanCharges::PAYMENTS} is the
     * retail annual's, and `plan_offers.instalment_payments` is a live offer's
     * — two figures that price different products, which `RegistryTest` fails
     * the build for harmonising.
     */
    public static function annualInInstalments(int $additionalLocations = 0): self
    {
        return new self(BillingTerm::Annual, true, self::locations($additionalLocations));
    }

    /**
     * Rebuild a selection from validated form input.
     *
     * ⚠️ **`instalments` IS IGNORED ON THE MONTHLY TERM RATHER THAN REFUSED**,
     * which is the one place this class is lenient. A form posting
     * `term=monthly&instalments=1` is a stale page or a hand-edited request, and
     * the safe reading of it is the plan that exists — refusing would turn a
     * cosmetic mismatch into a checkout that fails with nothing to fix.
     */
    public static function fromInput(?string $term, bool $inInstalments, int $additionalLocations = 0): self
    {
        $resolved = BillingTerm::tryFrom((string) $term) ?? BillingTerm::Monthly;

        if ($resolved === BillingTerm::Monthly) {
            return self::monthly($additionalLocations);
        }

        return $inInstalments
            ? self::annualInInstalments($additionalLocations)
            : self::annual($additionalLocations);
    }

    /**
     * The registry key holding the price of one location on this term.
     */
    public function priceKey(): string
    {
        return $this->term->priceKey();
    }

    private static function locations(int $additionalLocations): int
    {
        if ($additionalLocations < 0) {
            // A negative count would subtract a location's price from the plan,
            // which is a discount nobody authorised — and the arithmetic that
            // produces it (`locations - 1`) is exactly the guard the
            // `cashier-billing` skill already asks for.
            throw new InvalidArgumentException(
                "A subscription cannot be sold with {$additionalLocations} additional locations."
            );
        }

        return $additionalLocations;
    }
}

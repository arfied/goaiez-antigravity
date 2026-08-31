<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * An amount of money: integer minor units plus the currency they are in.
 *
 * `18` §Money handling and `29` §11.2 row 22: **integer cents plus a currency
 * code, everywhere, from day one.** $199.99 is `19999`. The spec singles this
 * out as one of the few things whose retrofit means rewriting billing, deals and
 * pricing across the schema, which is why the type arrives with the first
 * billing slice rather than with the first invoice.
 *
 * ⚠️ **THE CURRENCY IS PART OF THE VALUE, NOT CONTEXT AROUND IT.** A bare
 * integer of cents is the shape that lets `$50` of one currency be added to
 * `$50` of another and produce a number with no meaning. Every operation here
 * refuses a mismatch loudly instead, which is the only behaviour that cannot
 * silently produce a wrong bill.
 *
 * ⚠️ **THIS CLASS DELIBERATELY CANNOT FORMAT ITSELF, AND THE OMISSION IS THE
 * POINT.** `PlanPricing` is this codebase's one formatter and its own docblock
 * says why — "a second surface formatting cents itself is how `$199.99` and
 * `$199.9` end up on the same site" (decision 512). A `format()` or
 * `__toString()` here would be exactly that second surface, and it would be the
 * convenient one, so it would win. `PlanPricing::format()` takes one of these;
 * an `ArchitectureTest` assertion pins the absence so a helpful future edit has
 * to argue with a failing build rather than with a comment.
 *
 * There is no float in this file and there is no way to get one out of it. That
 * is the row 22 gate — "no money stored or compared as a float" — expressed as a
 * type rather than as a rule people remember.
 */
final readonly class Money
{
    /**
     * @param  int  $minorUnits  Cents for USD. Signed: a credit or an adjustment
     *                           is genuinely negative, and forbidding that here
     *                           would push callers back to bare integers.
     * @param  string  $currency  ISO 4217, uppercase.
     */
    private function __construct(
        public int $minorUnits,
        public string $currency,
    ) {}

    /**
     * The ordinary constructor.
     *
     * Private above and named here so that every construction site says which
     * unit it is in. `Money::of(19999, 'USD')` cannot be misread as dollars the
     * way `new Money(199.99)` could.
     */
    public static function of(int $minorUnits, string $currency): self
    {
        $normalised = strtoupper(trim($currency));

        if (preg_match('/^[A-Z]{3}$/', $normalised) !== 1) {
            throw new InvalidArgumentException(
                "Currency must be a three-letter ISO 4217 code, got [{$currency}]."
            );
        }

        return new self($minorUnits, $normalised);
    }

    /** Zero, in a stated currency. There is no currency-less zero on purpose. */
    public static function zero(string $currency): self
    {
        return self::of(0, $currency);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    /**
     * Repeat an amount a whole number of times — a per-location price times the
     * locations billed for.
     *
     * ⚠️ **INTEGER MULTIPLIER ONLY, WHICH RULES OUT PERCENTAGES BY CONSTRUCTION.**
     * A fractional multiplier needs a rounding policy, and a rounding policy
     * chosen casually is how a tax or proration line ends a cent away from
     * Stripe's own arithmetic. Decision 148 says there is no proration at all,
     * so nothing needs one today; whoever needs one brings the policy with them.
     */
    public function times(int $multiplier): self
    {
        return new self($this->minorUnits * $multiplier, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits
            && $this->currency === $other->currency;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minorUnits > $other->minorUnits;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minorUnits < $other->minorUnits;
    }

    /**
     * ⚠️ Comparison across currencies is a programming error, not a false.
     *
     * Returning `false` for "is $50 more than €50" would let a cost-cap check
     * pass by accident on the day a second currency exists — the check would
     * read as "under the cap" for every amount. Rule 43's caps are enforced with
     * comparisons like these, so the wrong answer here is a tenant billed
     * without a ceiling.
     */
    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot combine {$this->currency} with {$other->currency}: "
                .'money in two currencies has no common value without a rate, '
                .'and this codebase has no rate source.'
            );
        }
    }
}

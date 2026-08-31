<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Plan;
use App\Services\Config\DefaultsRegistry;

/**
 * Prices, read from the registry and formatted for a page.
 *
 * ⚠️ **THE MARKETING HOME USED TO PRINT `$199.99`, `$997`, `$99.99` AND `$497`
 * AS LITERALS IN A BLADE TEMPLATE**, which is exactly what doc `38` Part 2's
 * registry lint refuses, and it was the only such violation in the codebase when
 * CFG1 was built (decision 512). The cost of leaving it is specific rather than
 * theoretical: an operator changes a price in Ops, every billing path picks it
 * up, and the page that sells the plan keeps quoting the old number — with
 * nothing anywhere to notice, because the template is not where anyone looks
 * when a price changes.
 *
 * ONE FORMATTER, NOT ONE PER PAGE. A second surface formatting cents itself is
 * how "$199.99" and "$199.9" end up on the same site.
 *
 * ⚠️ **NOT LOCALISED, AND SAYING SO.** Decision 158 makes Spanish a launch
 * commitment and 159 requires a third language be configuration rather than a
 * refactor. This formats US dollars in the en-US convention with `number_format`
 * rather than `Number::currency()`, because the latter needs ext-intl and this
 * runs on the LCP-gated page. When the locale work lands, this is the one place
 * that changes — which is the whole reason it is one place.
 */
final class PlanPricing
{
    /**
     * Currency symbols we can print. Anything else prints its ISO code, so an
     * unmapped currency reads as "EUR 199.99" rather than being mislabelled with
     * a dollar sign — wrong-but-plausible is the failure worth avoiding here.
     *
     * @var array<string, string>
     */
    private const array SYMBOLS = ['USD' => '$'];

    /**
     * Per-instance memos.
     *
     * ⚠️ **NOT AN OPTIMISATION FOR ITS OWN SAKE.** The marketing home renders
     * five prices, and one SELECT per price plus one per currency lookup put ten
     * needless queries on the page that carries row 1's LCP gate (decision 519).
     * They live here rather than in `DefaultsRegistry` on purpose: the registry
     * is deliberately uncached (510) because a value written in one place and
     * read through a warm cache in another is a staleness bug no test sees. A
     * memo scoped to one instance of a formatter cannot outlive the render it
     * was built for.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $byPlan = [];

    private ?string $currency = null;

    public function __construct(
        private readonly DefaultsRegistry $registry = new DefaultsRegistry,
    ) {}

    /**
     * A per-plan money value, formatted for display.
     *
     * Whole amounts print without decimals — `$997`, not `$997.00` — because
     * that is how the owner's own figures are written and how the price appears
     * everywhere else it is quoted. Anything with cents keeps both digits.
     */
    public function display(Plan $plan, string $key): string
    {
        return self::format(Money::of($this->cents($plan, $key), $this->currency()));
    }

    /**
     * The one place cents become a string a person reads.
     *
     * Static and public because slice A gave money a type (`App\Support\Money`)
     * and the amounts that need printing stopped all being plan prices — an
     * invoice line or a credit balance is a `Money` with no `Plan` behind it.
     * The formatting rule is unchanged and still lives only here, which is the
     * whole point of decision 512: `Money` deliberately has no `format()` of its
     * own, so this method is the only exit from cents to a currency string.
     *
     * Whole amounts print without decimals — `$997`, not `$997.00` — because
     * that is how the owner's own figures are written and how the price appears
     * everywhere else it is quoted. Anything with cents keeps both digits.
     *
     * ⚠️ **NO DIVISION BY 100 ANYWHERE IN HERE, AND THE PREVIOUS VERSION HAD
     * ONE.** `number_format($cents / 100, 2)` reads as harmless — it is the view
     * boundary, and the skill's own rule is "format only at the view boundary" —
     * but it puts an IEEE-754 double in the one function the whole codebase
     * routes money through, so the row 22 gate's "never compared as a float"
     * would have had a float sitting one call away from every printed price.
     * `intdiv` and `%` are exact at every magnitude and cost nothing here.
     */
    public static function format(Money $money): string
    {
        $symbol = self::SYMBOLS[$money->currency] ?? $money->currency.' ';

        $cents = $money->minorUnits;

        // The sign is carried separately so that -50 cents prints as "-$0.50"
        // rather than "$0.50": intdiv(-50, 100) is 0, so a negative amount
        // smaller than one unit loses its sign if you take it from the whole
        // part. A credit printed as a charge is the wrong way round for the
        // reader in the way that matters most.
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        $whole = number_format(intdiv($absolute, 100));
        $fraction = $absolute % 100;

        if ($fraction === 0) {
            return $sign.$symbol.$whole;
        }

        return $sign.$symbol.$whole.'.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }

    /**
     * One plan's money value, in integer cents.
     *
     * ⚠️ Reads the batch and then re-asks the registry for anything the batch
     * does not hold, rather than falling back to a zero. A key absent from the
     * batch is either undeclared or withheld, and both must raise — silently
     * printing `$0` for the Limited price is exactly what decision 502 exists to
     * prevent.
     */
    private function cents(Plan $plan, string $key): int
    {
        $this->byPlan[$plan->value] ??= $this->registry->entitlementsFor($plan);

        $value = $this->byPlan[$plan->value][$key] ?? null;

        if (! is_int($value)) {
            return $this->registry->entitlementCents($plan, $key);
        }

        return $value;
    }

    private function currency(): string
    {
        if ($this->currency === null) {
            $stored = $this->registry->value('billing.currency');

            $this->currency = is_string($stored) ? $stored : 'USD';
        }

        return $this->currency;
    }
}

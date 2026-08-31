<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Splitting an annual price into instalments, without ever seeding one.
 *
 * ⚠️ **DECISION 2055 IS A RULE ABOUT WHERE A NUMBER LIVES, NOT ABOUT ARITHMETIC.**
 * "Two seeded numbers that are supposed to sum to a third is decision 754's trap
 * in a new hat: whichever is wrong is invisible, and the one people act on is
 * whichever the page happens to print." So the instalments are **derived from
 * the annual cents every time they are needed** and nothing anywhere stores
 * them. `RegistryTest` enforces the other half by failing the build on the
 * per-payment figures written as literals.
 *
 * ⚠️ **THE SPLIT COUNT IS PER OFFER AND NOT A CONSTANT (2092).** 2055 wrote
 * three payments; T137 R10's founder annual is two. The two founder rows prove
 * the rule between them — the add-on divides evenly and the base does not — so a
 * seeded pair would be right for one row and a cent wrong for the other.
 *
 * ## Where the remainder rides, and the one place the record disagrees with itself
 *
 * ⚠️ **THE REMAINDER RIDES ON THE FINAL PAYMENT.** That is `CLAUDE.md`'s
 * commercial-model section and decision 2055, which spells the three-payment
 * annual base as **33233 / 33233 / 33234** and says why: three payments a cent
 * short each would make the plan cost a cent less than the total every page
 * quotes, and "a 1¢ discount quietly falsifies every place that says $997".
 *
 * ⚠️ **AND THREE OTHER PLACES IN THE RECORD PUT IT ON THE FIRST PAYMENT
 * INSTEAD** (decision 2135). Decision 543 wrote "$332.34, $332.33 and $332.33 —
 * the first carrying the rounding". Decision 2092 quotes the owner's founder
 * figures as "**$250.00 + $249.99**", which is also the remainder first. Every
 * one of those sums to the same total, so no money is at stake and no test can
 * tell them apart from the total alone — **which is exactly why it is written
 * down rather than resolved quietly.** This class implements `CLAUDE.md`'s rule,
 * because `CLAUDE.md` overrides every doc; the consequence is that a page
 * printing the owner's ordering will show the larger payment second. 2055
 * already anticipated that and called it "a disclosure line, not a rounding
 * bug". **It costs one word from the owner to settle the ordering, and until
 * then this file is the single place it would change.**
 */
final class Instalments
{
    /**
     * Split a total into `$count` payments, largest last.
     *
     * ⚠️ **INTEGER MINOR UNITS ONLY, AND `intdiv` RATHER THAN `/`.** `Money`
     * refuses to produce a float at all and `PlanPricing` had a division by 100
     * removed from it for this reason (584): a double in the one function money
     * routes through puts the row 22 "never compared as a float" gate one call
     * away from every price. `intdiv` and `%` are exact at every magnitude.
     *
     * @return list<int> minor units, in payment order, summing exactly to $total
     *
     * @throws InvalidArgumentException
     */
    public static function split(int $total, int $count): array
    {
        if ($count < 1) {
            throw new InvalidArgumentException(
                "An instalment plan needs at least one payment; asked for {$count}."
            );
        }

        if ($total < 0) {
            throw new InvalidArgumentException(
                'An instalment plan cannot be built from a negative total: a refund is '
                .'not a payment schedule, and treating it as one would produce negative '
                .'charges the gateway would refuse one at a time.'
            );
        }

        if ($count > $total && $total > 0) {
            // A payment of zero minor units is not a payment: every gateway
            // refuses a zero-amount charge, so a schedule containing one is a
            // schedule that fails partway through — after the customer has
            // already paid the earlier instalments.
            throw new InvalidArgumentException(
                "A total of {$total} minor units cannot be split into {$count} payments "
                .'without at least one of them being zero, which no gateway will charge.'
            );
        }

        $each = intdiv($total, $count);
        $remainder = $total % $count;

        $payments = [];

        for ($i = 0; $i < $count; $i++) {
            // The remainder, on the last payment. See the class docblock: this
            // is CLAUDE.md's ordering, and the record carries a second one.
            //
            // Built by appending rather than by `array_fill()` plus an index
            // write, so the result is a genuine list — `array_fill()` returns a
            // keyed array Larastan will not narrow, and the honest fix is the
            // loop rather than a wider return type.
            $payments[] = $i === $count - 1 ? $each + $remainder : $each;
        }

        return $payments;
    }

    /**
     * The same split, as {@see Money} values.
     *
     * Provided so that no caller has to carry a bare integer of cents alongside
     * a currency it has to remember — the shape `Money`'s own docblock calls "the
     * one that lets $50 of one currency be added to $50 of another".
     *
     * @return list<Money>
     *
     * @throws InvalidArgumentException
     */
    public static function splitMoney(Money $total, int $count): array
    {
        return array_map(
            static fn (int $minorUnits): Money => Money::of($minorUnits, $total->currency),
            self::split($total->minorUnits, $count),
        );
    }
}

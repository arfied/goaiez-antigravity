<?php

declare(strict_types=1);

namespace App\Enums;

use InvalidArgumentException;

/**
 * What one unit on a `credit_ledger` row actually counts (decision 3419).
 *
 * ⛔ **THIS ENUM EXISTS BECAUSE THE THREE PRODUCTS ARE NOT COUNTED IN THE SAME
 * THING, AND THAT IS THE HAZARD 3331 PREDICTED BY NAME.** SMS and email are
 * counted in whole messages rather than in money. ⚠️ **THIS READ "ONE CREDIT,
 * ONE MESSAGE, T137 R9" AND THE SECOND HALF IS GONE** (9182): a unit is still a
 * whole thing and never a fraction, but a text carrying media spends **two** of
 * them, so *one unit = one message* is no longer the mapping. **AI is money**, and money at a
 * resolution finer than a cent: `ai_calls.retail_hundredths_cents` is hundredths
 * of a cent because a single call charges a fraction of one. A ledger holding all
 * three has to say, per row, which of those a `delta` of `1` means.
 *
 * ⚠️ **AND THE REGISTRY STATES THE AI FIGURE IN THE *OTHER* DENOMINATION.**
 * `credits.monthly_grant.ai_cents` is `5000` — integer **cents**, $50, decision
 * 9180 reversing 3412's $30 — while this ledger stores hundredths. 3331: *"reading one straight into
 * the other under-grants by a hundred with the balance looking plausible
 * throughout."* No exception, no obviously wrong number; a tenant silently
 * receives 1% of what they were promised.
 *
 * ✅ **SO THE CONVERSION IS ONE METHOD ON ONE ENUM** — {@see self::fromCents()} —
 * reached only through {@see CreditProduct::ledgerUnitsFromGrant()}, and
 * `CreditProductsTest` drives a 100× error red from three directions: the
 * arithmetic itself, the granted balance, and the number of real AI calls the
 * grant buys.
 */
enum CreditUnit: string
{
    /**
     * One whole message. SMS and email.
     *
     * A send is not money and is deliberately not `App\Support\Money` — the
     * `credit_ledger` migration has said so since the table was created: *"a
     * credit is a unit of send, priced at $50/1,000 when bought, and conflating
     * the count with the cents paid for it is how a pack bought at one price gets
     * valued at another after a price change."*
     */
    case Send = 'send';

    /**
     * One hundredth of a cent. AI, and only AI.
     *
     * ⛔ **THE FINER UNIT WINS AND THE CHOICE IS FORCED RATHER THAN STYLISTIC.**
     * The AI debit's source is `ai_calls.retail_hundredths_cents`, and a single
     * call charges something like 1.23c — **123 hundredths, which is not a whole
     * number of cents**. Storing this pool in cents would leave two options and
     * both are wrong by a whole order of magnitude: round down and every ordinary
     * call is free, round up and a call costing a hundredth of a cent charges a
     * full one. There is no honest integer-cent representation of the thing being
     * debited, so the ledger takes the unit the debit already arrives in and the
     * *grant* is what converts.
     */
    case HundredthsOfACent = 'hundredths_of_a_cent';

    /**
     * How many of this unit make one cent.
     *
     * ⚠️ **`Send` REFUSES RATHER THAN ANSWERING `1`.** A send has no price in this
     * table by construction — the money for a pack of SMS lives on the purchase
     * row, never on the ledger row — so "how many sends are a cent" is a question
     * with no true answer, and returning `1` would let a caller convert a cents
     * figure into a send count and get a plausible number back. A `match` with a
     * throwing arm is the only shape where the nonsense case is loud.
     *
     * @throws InvalidArgumentException for a unit that does not denominate money.
     */
    public function perCent(): int
    {
        return match ($this) {
            self::HundredthsOfACent => 100,
            self::Send => throw new InvalidArgumentException(
                'A send is not money, so it has no number of units to a cent. Asking this '
                .'of CreditUnit::Send means a cents figure is about to be read into a pool '
                .'that counts messages — which is the conversion decision 3331 names as the '
                .'one that fails silently and plausibly.'
            ),
        };
    }

    /**
     * A figure in integer cents, expressed in this unit.
     *
     * ⛔ **THE ONE CONVERSION BOUNDARY IN THIS APPLICATION** (3419). Every other
     * file works in ledger units; only a *grant seed* arrives in cents, and only
     * through {@see CreditProduct::ledgerUnitsFromGrant()}. If a second place ever
     * multiplies or divides by a hundred to reach an AI balance, the factor can
     * drift and the balance will still look plausible — which is exactly 3331.
     *
     * @throws InvalidArgumentException for a unit that does not denominate money.
     */
    public function fromCents(int $cents): int
    {
        return $cents * $this->perCent();
    }

    /**
     * A figure in this unit, expressed in integer cents.
     *
     * ✅ **THE INVERSE 3571 SAYS IS OWED**, added here rather than divided by a
     * hundred a third time. 3550 records the first re-derivation —
     * `App\Livewire\Account\Credit::asCents()`, a private `intdiv` written because
     * that lane did not own this file — and a support console that prints an AI
     * balance as money, and the same console turning an operator's typed cents
     * into a ceiling comparison, would have been the second and the third.
     * **A factor cannot drift when there is one of it**, which is 3331's argument
     * reached from the other direction.
     *
     * ⚠️ **IT TRUNCATES RATHER THAN ROUNDS, AND THAT IS THE SAFE DIRECTION.**
     * Showing anybody a cent they cannot spend is worse than withholding a
     * fraction of one, and nothing downstream does arithmetic on the result — it
     * is a figure to print. `intdiv` is exact at every magnitude; `/ 100` would
     * put an IEEE-754 double on a money path, which `PlanPricing::format()` had to
     * have removed from it once already.
     *
     * ⚠️ **`Send` REFUSES HERE FOR THE REASON IT REFUSES IN {@see self::perCent()}.**
     * A message has no price on this table, so "how many cents is a text credit"
     * has no true answer — and a `0` returned here would print every text balance
     * as free.
     *
     * @throws InvalidArgumentException for a unit that does not denominate money.
     */
    public function toCents(int $units): int
    {
        return intdiv($units, $this->perCent());
    }
}

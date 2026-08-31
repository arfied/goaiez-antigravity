<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Does this string look like a card number? The tripwire, in one place.
 *
 * ⚠️ **IT LIVED AS A PRIVATE METHOD ON `AuthorizeNetCheckoutRequest` AND HAD TO
 * MOVE THE MOMENT A SECOND SURFACE NEEDED IT.** The card-replacement press on
 * `/account/plan` reaches PHP as a Livewire action rather than as a request, so
 * no form request can run over it — and once a field a browser autofills from
 * the **card record** exists on both pages, both surfaces need the same question
 * asked. ⛔ **A second copy of a Luhn implementation is 8460's shape even while
 * both copies are correct**, and the copy that goes stale is the one on the
 * surface nobody is looking at.
 *
 * ⚠️ **LUHN RATHER THAN "13 TO 19 DIGITS", AND THE DIFFERENCE MATTERS IN THE
 * DIRECTION OF FALSE POSITIVES.** An Accept.js nonce is a long numeric string —
 * `9471056021205027705001` in the vendor's own example — so a
 * length-and-digits test would refuse legitimate nonces at some rate, which is a
 * checkout that fails for a fraction of customers with no pattern anybody can
 * see. Luhn is what a card number passes and an arbitrary numeric token passes
 * only by coincidence, at roughly one in ten.
 *
 * ⚠️ **AND IT IS A TRIPWIRE, NOT A CONTROL.** It cannot catch every PAN and is
 * not the thing keeping card data out — that is Accept.js putting the card
 * fields outside our form in the first place, with no `name` attribute on any of
 * them. What this catches is the day that stops being true, which is the day
 * nobody would otherwise notice.
 */
final class CardNumberShape
{
    /**
     * A digits-and-separators string of card length, passing the Luhn check.
     *
     * ⛔ **THE VALUE IS NEVER RETURNED, LOGGED OR QUOTED BY ANY CALLER** — the
     * whole point of asking is that it may be a card number, so the answer is a
     * boolean and the caller may say only which field it came from.
     */
    public static function looksLikeOne(string $value): bool
    {
        $digits = preg_replace('/[\s-]/', '', $value) ?? '';

        if (preg_match('/^\d{13,19}$/', $digits) !== 1) {
            return false;
        }

        $sum = 0;
        $double = false;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];

            if ($double) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }
}

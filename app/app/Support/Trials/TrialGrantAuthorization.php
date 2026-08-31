<?php

declare(strict_types=1);

namespace App\Support\Trials;

use App\Exceptions\TrialGrantRefused;

/**
 * Proof that the abuse controls were asked before an allowance was minted.
 *
 * ⛔ **THIS TYPE EXISTS FOR A CALLER THAT DOES NOT EXIST YET, AND THAT IS THE
 * POINT.** Decision 3117 records that the no-card trial's fraud surface is
 * unarmed *only because nothing mints the 500 SMS* — `CreditKind::Grant` is
 * constructed nowhere in `app/` and a lint reddens on anything that does (3119).
 * The protection therefore disappears the instant somebody builds the funder, in
 * whichever slice happens to do it, and **the fraud controls are not that slice's
 * stated job**. So the controls ship first, in a shape the grant lane inherits
 * rather than has to invent.
 *
 * ## Why a type and not a boolean
 *
 * `PlaceConfirmation::confirm()`'s literal `true $confirmed` argument is the
 * precedent, and its docblock gives the reason: an `if (…)` inside a method is a
 * line somebody can delete with no test necessarily noticing. Here the grant
 * signature takes a `TrialGrantAuthorization`, and the only way to build one is
 * from a verdict that carries no refusals — because **this constructor throws on
 * anything else**. A caller who wants to skip the controls has to construct a
 * fake eligible verdict on the line above, which is a visible lie in a diff
 * rather than an absence nobody sees.
 *
 * ⚠️ **WHAT IT CANNOT DO, SAID PLAINLY BECAUSE THE OPPOSITE CLAIM IS EXACTLY THE
 * FAILURE `CLAUDE.md` RECORDS THREE TIMES** ("a protection layer asserted before
 * it is true"): **nothing forces the grant lane to accept this parameter at all.**
 * No lint can require it — an assertion about the callers of a method with no
 * callers is vacuous, which is 256's shape — and writing one that matches nothing
 * would be worse than writing none. What actually routes the next author here is
 * `BillingTest`'s funding lint (3119), which goes red on the first
 * `CreditKind::Grant` and names decisions 3115, 3116 and 3117 in its own
 * docblock.
 *
 * ⚠️ **AND IT AUTHORIZES A GRANT, NEVER A SEND.** Holding one of these says the
 * account may be *funded*. Whether a message may go out is a consent question and
 * is answered somewhere else entirely, unchanged by this slice (2066:
 * "suppression and consent unchanged").
 */
final readonly class TrialGrantAuthorization
{
    /**
     * @throws TrialGrantRefused when the verdict carries any refusal at all.
     */
    public function __construct(public TrialGrantVerdict $verdict)
    {
        if ($verdict->refusals !== []) {
            throw TrialGrantRefused::for($verdict->businessId, $verdict->refusals);
        }
    }

    public function businessId(): int
    {
        return $this->verdict->businessId;
    }
}

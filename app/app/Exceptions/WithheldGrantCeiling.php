<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\CreditProduct;
use App\Enums\UserRole;
use RuntimeException;

/**
 * A support operator asked to grant a product whose grant ceiling nobody has
 * ruled, so the grant is refused rather than bounded by a guessed figure
 * (decision 3426).
 *
 * ⛔ **THE FIGURE MAY NOT BE INVENTED, AND THE TEMPTING INVENTION IS THE SMS
 * ONE.** `28` §9.1 gives exactly one number — *"credits grant ≤ 500"* — and it is
 * 500 **texts**. Read onto email it is half a tenant's monthly allotment; read
 * onto AI it is five cents, because that pool counts hundredths of a cent (3420).
 * The same integer is three different sizes of gift, and a ceiling that quietly
 * became one of them would be policy nobody chose, enforced by a service, on a
 * screen that spends money.
 *
 * ✅ **SO IT FAILS CLOSED, WHICH IS `WithheldRegistryValue`'s POSTURE ONE FLOOR
 * DOWN** (502): the conservative answer for an unset limit is not a smaller
 * number, it is refusing to act. `ImportStatementUnavailable` is the nearer
 * precedent — a platform state rather than a caller's mistake, given its own type
 * so the screen can recognise it without matching a message.
 *
 * ## Why this is not `CreditMovementRefused`
 *
 * That exception means *you asked for too much*, and its message tells the
 * operator to ask somebody with a higher limit. This one means **nobody has a
 * limit for this product yet**, and the operator did nothing wrong. Conflating
 * them would tell an agent to escalate an amount when the amount was never the
 * problem — and would put an unruled ceiling on the same footing as a breached
 * one in every `catch` in the console.
 *
 * ## If you are here because this threw
 *
 * The fix is the owner setting the two numbers, not a fallback. Until then a
 * `support_lead` or a `super_admin` can grant these products — `28` §9.1 gives
 * *those* roles no figure on any product, which is a different silence and is
 * not this one.
 */
final class WithheldGrantCeiling extends RuntimeException
{
    /**
     * ⚠️ **THE MESSAGE IS FOR THE PERSON ON SHIFT, AND IT USED TO BE FOR US**
     * (3845). It ended *"Decision 3426 left the per-product grant limits open …
     * §9.1's figure of 500 is 500 text messages"* — a decision number and a
     * specification section quoted at a support agent who wanted to help a
     * customer, and ungrammatical besides, because a role label reads as a name
     * (*"how much email credit A support agent may grant"*). `22`: outcome
     * language, naming what the reader controls. **The reasoning has not moved,
     * it has stopped being read aloud** — it is in this class's own docblock,
     * where the person who needs a decision number is the person already reading
     * the code.
     */
    public static function for(UserRole $role, CreditProduct $product): self
    {
        return new self(
            'There is no agreed limit yet on how much '.self::noun($product).' a '
            .mb_strtolower($role->label()).' can add, so this one has to be done by '
            .'somebody else. Ask a support lead or a super admin — they can add it today.'
        );
    }

    /**
     * The product, as a sentence about it reads.
     *
     * ⚠️ **NOT THE SCREEN'S HEADINGS AND DELIBERATELY NOT SHARED WITH THEM.**
     * `Livewire\Support\Accounts` labels a radio button — a noun phrase standing
     * on its own — and this is the middle of a sentence about a limit. One list
     * serving both would make one of the two read badly, which is how a screen
     * ends up saying "how much Emails a support agent may grant".
     */
    private static function noun(CreditProduct $product): string
    {
        return match ($product) {
            CreditProduct::Sms => 'text credit',
            CreditProduct::Email => 'email credit',
            CreditProduct::Ai => 'AI credit',
        };
    }
}

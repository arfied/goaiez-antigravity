<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * One refusal for every way `POST /reset-password` can fail (9622).
 *
 * ⛔ **THIS ENDPOINT IS A SECOND ACCOUNT-ENUMERATION ORACLE AND NOTHING IN THIS
 * REPOSITORY HAD EVER SAID SO** — 9505 names the route in passing as the other
 * thing `Features::resetPasswords()` registers, and reports it as *unthrottled*
 * rather than as *answering*. It answers. Measured at the route on 2026-08-25,
 * with a token made up on the spot and no reset ever requested:
 *
 *   an address that HAS an account   `302`, *"This password reset token is invalid."*
 *   an address that has NONE         `302`, *"We can't find a user with that email address."*
 *
 * `PasswordBroker::validateReset()` is the branch —
 * `getUser()` first, `tokens->exists()` second — so **the token does not have to
 * be real, or even plausible, for this door to answer**. That makes it strictly
 * cheaper to abuse than `/forgot-password`, which at least sends nothing to an
 * unknown address: this one needs no mailbox, no delivery and no waiting.
 *
 * ⚠️ **AND IT IS A THIRD SHAPE, NOT THE OTHER TWO WITH A DIFFERENT URL.**
 * `/forgot-password` leaks by *success vs failure*; `/register` leaks by a
 * uniqueness rule that is deliberate product behaviour (9506); this one leaks by
 * **which of two failures** — so an application that had closed the first two by
 * making success and failure alike would still be answering here.
 *
 * ⛔ **THE SUCCESS ARM IS FORTIFY'S AND IS NOT TOUCHED.** A completed reset
 * redirects and signs the person in, which is not something this class can or
 * should make look like a refusal — uniformity here is *among the failures*,
 * which is all that is needed: succeeding requires a token that was mailed to
 * the address, so the only caller who can tell the arms apart is the account
 * holder.
 *
 * ⚠️ **`RESET_THROTTLED` REACHES THIS CLASS TOO** and is covered by the same
 * single message, for {@see PasswordResetLinkRequestedResponse}'s reason: an
 * arm only an existing account can reach is an oracle whatever its wording.
 *
 * ⛔ **THIS CLASS DECLARES NO CONSTRUCTOR, AND THE ABSENCE IS THE POINT RATHER
 * THAN AN OMISSION.** Fortify resolves the contract with
 * `app($contract, ['status' => $status])`, and Laravel's container ignores an
 * override for a dependency the constructor does not declare — so the broker's
 * answer is discarded at the container boundary and never reaches an instance
 * at all. ⚠️ **A PROMOTED `private readonly string $status` WAS WRITTEN FIRST
 * AND LARASTAN LEVEL 8 REFUSED IT** — *"never read, only written"* — and it was
 * right for a better reason than tidiness: **a property nothing reads is an
 * invitation to read it**, and reading it is exactly how the branch gets back
 * into the response this class exists to make uniform.
 */
final class PasswordResetFailedResponse implements FailedPasswordResetResponse
{
    /**
     * The one thing a failed reset ever says.
     *
     * ⚠️ **DELIBERATELY THE MAGIC LINK'S SENTENCE, ONE DOOR OVER.**
     * `MagicLinkController::show()` answers *"That sign-in link has already been
     * used or has expired. Ask for a new one."* for unknown, expired,
     * already-used and account-since-deleted, with the comment *"Distinguishing
     * them tells whoever is holding a stale link which kind of stale it is."*
     * That is this situation exactly, and the wording is matched on purpose so
     * the two passwordless doors read as one product rather than as two
     * accidents.
     */
    public const string MESSAGE = 'That password reset link has already been used or has expired. Ask for a new one.';

    /**
     * @throws ValidationException
     */
    public function toResponse($request): Response
    {
        /** @var Request $request */
        if ($request->wantsJson()) {
            // Fortify's own shape for a headless client, with one message
            // instead of the broker's. A 422 is correct here and is not the
            // leak: it is the same 422 on both arms.
            throw ValidationException::withMessages([
                'email' => [self::MESSAGE],
            ]);
        }

        // ⚠️ `withInput()` IS KEPT, AND THE REASONING IS THE OPPOSITE OF THE
        // ONE NEXT DOOR. {@see PasswordResetLinkRequestedResponse} had to drop
        // it, because there Fortify flashes old input on the *failure* arm and
        // not on the success one — so its presence in the session was itself
        // the answer. Here every failure travels through this one class, so the
        // flashed bag is identical whichever branch produced it, and dropping
        // it would cost the person their typed address for nothing.
        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => self::MESSAGE]);
    }
}

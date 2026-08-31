<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * One answer for every outcome of `POST /forgot-password` (9505, 9621).
 *
 * ⛔ **THIS CLASS IS BOUND TO BOTH OF FORTIFY'S CONTRACTS AT ONCE, AND THAT IS
 * THE WHOLE OF WHAT IT DOES.** `FortifyServiceProvider` binds it to
 * {@see SuccessfulPasswordResetLinkRequestResponse} **and** to
 * {@see FailedPasswordResetLinkRequestResponse}. Two bindings of one class is
 * the mechanism: a second class rendering "the same" message is two strings
 * that can drift, and the property being kept is that **the two arms are
 * indistinguishable**, not that they happen to read alike today.
 *
 * ⚠️ **THE ORACLE WAS AN EXPLICIT SENTENCE, NOT A TIMING CHANNEL.**
 * `PasswordBroker::sendResetLink()` returns `INVALID_USER` for an address with
 * no account, Fortify branches on it, and the failure response rendered
 * `trans('passwords.user')` — *"We can't find a user with that email address."*
 * Measured at the route on 2026-08-25: a known address answered `302` with
 * `status` = *"We have emailed your password reset link."*, an unknown one
 * answered `302` with an `email` **error bag** carrying that sentence. Same
 * status code, different flash — which is why 9505's `withoutExceptionHandling()`
 * probe reported a status pair that the real stack does not produce.
 *
 * ⚠️ **TIMING WAS ALREADY DEFENDED AND STILL IS NOT PERFECT.**
 * `sendResetLink()` runs inside a 200 ms `Timebox`, so the `INVALID_USER` arm is
 * padded to the same duration as the token write. ⛔ **The SEND is not inside
 * it**: `$user->sendPasswordResetNotification($token)` is called within the
 * timebox but a queued notification that runs in-process on
 * `QUEUE_CONNECTION=sync` can exceed 200 ms, at which point the timebox stops
 * hiding anything. That is 9504's finding about `MagicLinkController` restated
 * at the other door, it is closable by not running `sync` — which is what
 * production runs and what `.env.example` ships — and **this class does not
 * close it.**
 *
 * ⚠️ **`RESET_THROTTLED` IS THE THIRD ARM AND IS THE ONE THAT WOULD HAVE BEEN
 * MISSED.** `config/auth.php` sets `passwords.users.throttle` to 60, so asking
 * twice inside a minute for an address that **does** have an account returns
 * `passwords.throttled` rather than `passwords.sent` — an arm that can only be
 * reached by an address with an account, and therefore an oracle of its own.
 * Fortify routes it through the *failed* contract, so binding both contracts
 * closes it for free; binding only `INVALID_USER`'s path would not have.
 *
 * ⛔ **AND THE MESSAGE IS NOT FORTIFY'S `passwords.sent`.** *"We have emailed
 * your password reset link"* is a claim this application cannot keep on the
 * unknown-address arm, where nothing was emailed at all — so a uniform response
 * built on that string would make the product lie to close a leak.
 * `MagicLinkController::store()` already answers *"If that address has an
 * account, a sign-in link is on its way"* on both of its arms; this is the same
 * sentence at the other door, and the two are deliberately alike.
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
final class PasswordResetLinkRequestedResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    /**
     * The one thing this endpoint ever says.
     *
     * ⚠️ **A CONSTANT SO THE TEST CAN ASSERT THE TWO ARMS AGAINST EACH OTHER
     * RATHER THAN AGAINST A COPY OF THE STRING.** 9509 records the shape that
     * goes wrong here: asserting the two arms are *equal* is satisfied by both
     * of them failing identically, so the test asserts they are equal **and**
     * that the shared answer is this.
     */
    public const string MESSAGE = 'If that address has an account, a password reset link is on its way.';

    public function toResponse($request): Response
    {
        /** @var Request $request */
        if ($request->wantsJson()) {
            // ⛔ 200 ON BOTH ARMS. Fortify's failure response throws a
            // ValidationException here, which is a 422 with the sentence in it
            // — so a JSON client saw the whole oracle in the status code as
            // well as the body. A headless caller gets the same 200 and the
            // same message a browser does.
            return new JsonResponse(['message' => self::MESSAGE], 200);
        }

        // ⛔ NO `withInput()`, AND ITS ABSENCE IS LOAD-BEARING RATHER THAN
        // TIDY. Fortify flashes `$request->only('email')` back on the FAILURE
        // arm and not on the success one, so `_old_input` being present in the
        // session was a second, quieter copy of the same answer — visible to
        // exactly the caller who was asking. Neither arm flashes it now.
        return back()->with('status', self::MESSAGE);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\Auth\SecondFactor;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest as FortifyTwoFactorLoginRequest;
use PragmaRX\Google2FA\Exceptions\Google2FAException;

/**
 * Fortify's challenge request, with the two ways it could 500 closed.
 *
 * `POST /two-factor-challenge` reaches `hasValidCode()` with the pending user
 * from `login.id`, and Fortify's version decrypts that user's secret and hands
 * it to Google2FA with no guard on either step. Two real requests on
 * 2026-08-31 found both holes:
 *
 *   05:42  the secret decrypted to a value that is not base32, and Google2FA
 *          threw `IncompatibleWithGoogleAuthenticatorException` — a 500 on the
 *          sign-in screen for a code that was simply wrong.
 *   05:45  the same session posted again after the secret had been cleared,
 *          and `decrypt(null)` threw `DecryptException` — a 500 for a person
 *          who no longer had a second factor to present.
 *
 * Both were then "fixed" by stubbing `SecondFactor::required()` to `false`,
 * which switched mandatory staff 2FA off platform-wide. This class is the fix
 * that lets that rule come back.
 *
 * Recovery codes are unaffected: `store()` checks `validRecoveryCode()` before
 * `hasValidCode()`, so a person whose stored secret is unusable can still get
 * in with one and then reset the factor from the setup screen.
 */
final class TwoFactorLoginRequest extends FortifyTwoFactorLoginRequest
{
    /**
     * Determine if the request has a valid two factor code.
     */
    public function hasValidCode(): bool
    {
        if (! $this->code) {
            return false;
        }

        $user = $this->challengedUser();

        // No secret means there is nothing to challenge against. The pending
        // sign-in is stale — the factor was removed after the challenge was
        // issued — so the honest answer is the one Fortify gives for a missing
        // user: forget it and start the sign-in again.
        if (! $user instanceof User || $user->two_factor_secret === null) {
            $this->session()->forget(['login.id', 'login.remember']);

            throw new HttpResponseException(
                redirect()->route('login')->withErrors([
                    'email' => 'Two-step sign-in is no longer set up on that account. Please sign in again.',
                ]),
            );
        }

        $secret = SecondFactor::decrypted($user);

        if ($secret === null) {
            $this->refuseUnusableSecret($user, 'undecryptable');
        }

        try {
            $valid = app(TwoFactorAuthenticationProvider::class)->verify($secret, $this->code);
        } catch (Google2FAException $e) {
            $this->refuseUnusableSecret($user, $e::class);
        }

        if ($valid) {
            $this->session()->forget('login.id');
        }

        return $valid;
    }

    /**
     * A stored secret that cannot produce codes is an operator problem, not a
     * wrong code — say so, and name the account in the log (never the secret).
     */
    private function refuseUnusableSecret(User $user, string $reason): never
    {
        Log::warning('two-factor challenge refused: the stored secret is unusable', [
            'user_id' => $user->getKey(),
            'reason' => $reason,
        ]);

        throw ValidationException::withMessages([
            'code' => 'The two-step key stored for this account cannot be used. '
                .'Sign in with a recovery code, or ask an administrator to reset two-step sign-in.',
        ]);
    }
}

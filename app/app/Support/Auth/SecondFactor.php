<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Fortify;

/**
 * The one place this application decides anything about a second factor
 * (`28` §9.1: *"mandatory 2FA (TOTP or passkey) for every internal account"*).
 *
 * Three questions, and keeping them together is the point — they disagreed
 * badly while they were spread across Fortify's pipeline and two of our own
 * controllers, and neither disagreement had a symptom.
 *
 * ## ⚠️ 1. `held()` — is a factor actually demanded of this person at login?
 *
 * **Not "has a passkey", and `28` §9.1's own wording is narrowed here on
 * purpose.** A passkey is genuinely two factors when it is used — possession of
 * the authenticator plus the user verification it performs — but in this
 * application it is one of *four* ways in, and holding one does not stop the
 * other three. A staff account with a passkey and a password signs in with the
 * password and is challenged for nothing; the passkey sits there proving
 * nothing about the session that was actually opened.
 *
 * A passkey could only satisfy a mandatory-2FA rule if it were the **only** way
 * into the account, and it cannot be: magic link needs nothing but the address,
 * and every account has one. So counting passkeys today would be a protection
 * asserted before it is true (314–316) — the rule would read as satisfied by a
 * credential nothing ever asks for.
 *
 * **The clause to add when it becomes true** is a passkey *challenge* at login,
 * which needs the WebAuthn browser ceremony that is not yet wired into the
 * frontend build (`CLAUDE.md`, known gaps). Add it here, in this method, and
 * nowhere else.
 *
 * ## ⚠️ 2. `challenge()` — three of the four ways in never asked
 *
 * Fortify's challenge lives in **its own login pipeline**, which only `POST
 * /login` runs through: `RedirectIfTwoFactorAuthenticatable` is a pipe in that
 * pipeline and nothing else invokes it. `MagicLinkController` and
 * `OauthLoginController` call `Auth::login()` directly and correctly — that is
 * how those flows work — and in doing so **walked straight past a confirmed
 * second factor**.
 *
 * The consequence is worse than a gap, because it is on the cheapest route:
 * an internal account with mandatory 2FA, faced with a code to type, can ask
 * for a magic link instead and be signed in with a single factor. The stricter
 * the requirement, the more attractive the door around it. **A mandatory
 * control that the login page itself offers a way around is not a control**,
 * and no test would have caught it — nothing in this codebase had a confirmed
 * factor, so the branch had never been taken on any route.
 *
 * `challenge()` is the same handshake Fortify performs, exposed for the two
 * controllers that are outside its pipeline: stash the pending user, fire the
 * event, and send them to the screen. It returns null when there is nothing to
 * ask for, so a caller reads as "challenge if needed, otherwise carry on".
 *
 * ## 3. `required()` — who must hold one
 *
 * Internal staff, by population and not by power — `isPlatformStaff()`, so a
 * `cs_readonly` on their first week is covered exactly as a `super_admin` is.
 * `canAdministerPlatform()` would be the wrong predicate here and it is the one
 * every other gate in this application reaches for (560): the question is not
 * "how much can they break" but "are they one of ours", and §9.1 says *every
 * internal account*.
 *
 * Tenants are deliberately **not** required to hold one. Nothing in `29` asks
 * for it, `CLAUDE.md` forbids adding a tenant-facing toggle, and mandating a
 * second factor on a business owner who signed up to fix their Google reviews
 * is a support surface this product does not want. They may still turn one on,
 * and if they do, `challenge()` honours it on all four routes.
 *
 * ## How long the session it opens may last is `StaffSession`'s
 *
 * This class answers *"who is signing in, and have they proved it"*. `App\
 * Support\Auth\StaffSession` answers *"and for how long"* — `28` §9.1's other
 * hard access rule, in the sentence directly after this one's. The only place
 * the two touch is `challenge()`'s `login.remember` below, and that is exactly
 * where the second rule was being quietly defeated.
 */
final class SecondFactor
{
    /**
     * Whether this person will actually be asked for a second factor at login.
     *
     * Mirrors Fortify's own challenge condition rather than restating it: with
     * `confirm => true` an unconfirmed secret is a half-finished enrolment and
     * Fortify does not challenge on it, so neither may this. Reading the config
     * option rather than hardcoding the confirmed-at check keeps the two from
     * drifting if that option is ever changed.
     */
    public static function held(User $user): bool
    {
        if (Fortify::confirmsTwoFactorAuthentication()) {
            return $user->two_factor_secret !== null
                && $user->two_factor_confirmed_at !== null;
        }

        return $user->two_factor_secret !== null;
    }

    /**
     * Whether this person must hold one before they may use the console.
     */
    public static function required(User $user): bool
    {
        return $user->role->isPlatformStaff();
    }

    /**
     * Whether enrolment has been started and not finished.
     *
     * ⚠️ **The third state, and the one an implementation forgets.** With
     * `confirm => true` a secret can exist while the factor does not work,
     * because somebody pressed "Turn on" and closed the tab — so `held()` is
     * false and *"not started"* is also false. A screen that models two states
     * sends that person back to the beginning and mints them a second secret,
     * silently orphaning the one they may already have scanned.
     */
    public static function enrolling(User $user): bool
    {
        return $user->two_factor_secret !== null && ! self::held($user);
    }

    /**
     * The plaintext key, for showing beside the QR code during enrolment.
     *
     * Here rather than at the screen so that the two-factor columns have one
     * reader in `app/` and the lint that says so needs no allowlist.
     *
     * ⚠️ `Fortify::currentEncrypter()`, never the bare `decrypt()` helper.
     * Fortify supports rotating the encrypter used for these columns
     * independently of the application key, and the helper would read the right
     * value today and the wrong one on the day somebody uses that — a failure
     * with no symptom until an enrolment silently stops matching.
     *
     * Returns null unless enrolment is genuinely in progress: a key handed out
     * before a secret exists has nothing to decrypt, and one handed out
     * afterwards is a live credential printed on a page for no reason.
     */
    public static function enrolmentKey(User $user): ?string
    {
        if (! self::enrolling($user)) {
            return null;
        }

        $secret = $user->two_factor_secret;

        return is_string($secret)
            ? Fortify::currentEncrypter()->decrypt($secret)
            : null;
    }

    /**
     * Ask for the second factor before completing a sign-in, if one is held.
     *
     * For the two login routes that sit outside Fortify's pipeline. Call it
     * **before** `Auth::login()` and return its result when it is not null: the
     * person is not signed in yet, and the point is that they do not become
     * signed in until the factor is presented.
     *
     * ⚠️ The session keys are Fortify's, not ours — `TwoFactorAuthenticated
     * SessionController` reads `login.id` to find the pending user, so a
     * differently named key would produce a challenge screen that can never be
     * satisfied.
     *
     * ⚠️ **`login.remember` is a third remember-me site, and decision 670 named
     * only the two `Auth::login()` calls.** It is the site that matters: §9.1
     * mandates a factor for every internal account, so a staff sign-in on either
     * of those two routes *always* returns here and completes inside Fortify's
     * controller, which reads this key. Hardcoding `true` here would have left
     * remember-me alive for precisely the population `28` §9.1's ceiling applies
     * to, with both of the sites 670 named visibly corrected — a control that
     * reads as built and is defeated on the only path it governs.
     */
    public static function challenge(Request $request, User $user): ?RedirectResponse
    {
        if (! self::held($user)) {
            return null;
        }

        $request->session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => StaffSession::remember($user),
        ]);

        TwoFactorAuthenticationChallenged::dispatch($user);

        return redirect()->route('two-factor.login');
    }
}

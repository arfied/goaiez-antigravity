<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MagicLinkRequest;
use App\Models\User;
use App\Services\MagicLinkService;
use App\Support\Auth\SecondFactor;
use App\Support\Auth\StaffSession;
use App\Support\HashedIp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sign in by email link.
 *
 * The fallback that has to keep working: `laravel/passkeys` is v0.2.1 and on the
 * login path, so this is the passwordless route that does not depend on it.
 */
final class MagicLinkController extends Controller
{
    /**
     * Ask for a link.
     *
     * Always the same response, whether or not the address has an account. The
     * send is queued, so the response *time* does not answer the question either
     * — a synchronous send makes account existence measurable with a stopwatch,
     * which is the version of this endpoint that looks fine in review.
     *
     * ⛔ **THAT SECOND SENTENCE IS TRUE OF THE RUNNING INSTALL AND FALSE ON
     * `QUEUE_CONNECTION=sync` — ADDED 2026-08-25 (9504), AND IT IS THE HALF
     * 9500 DID NOT CLOSE.** `sync` executes the job in the dispatching process,
     * so on that driver *"the send is queued"* is not a description of anything
     * and the address that has an account pays a full transport round trip
     * inside this request. **The status codes are equal on every driver since
     * 9500; the response times are not, and this application cannot make them
     * be.** The remedies are a queue driver that is not `sync` — which is what
     * `.env.example` ships and what production runs — or a constant-time
     * endpoint, which is a different slice and would have to cover the token
     * write as well as the send.
     *
     * ⚠️ **NOT REWORDED AWAY, BECAUSE THE SENTENCE IS THE REASON THE ENDPOINT
     * IS SHAPED LIKE THIS.** What was wrong was reading it as a fact about
     * every deployment, which is `CLAUDE.md`'s *a seed is not a deployment* one
     * layer down: the queue connection is an `.env` line, and no document in
     * this repository may state what a running install has on.
     */
    public function store(MagicLinkRequest $request, MagicLinkService $links): RedirectResponse
    {
        $links->request(
            (string) $request->validated('email'),
            HashedIp::of($request),
        );

        return back()->with('status', 'If that address has an account, a sign-in link is on its way.');
    }

    /**
     * Spend a link and sign in.
     *
     * Token in the path rather than a query string: query strings reach Referer
     * headers, access logs and analytics, and this one is a credential for the
     * fifteen minutes it lives.
     *
     * ⚠️ **RETURNS `Response` RATHER THAN `RedirectResponse` BECAUSE THE LAST
     * LINE IS NOW SOMEBODY ELSE'S ANSWER** — see the sign-in destination note
     * at the bottom of this method. The refusal arm above still returns a
     * redirect.
     */
    public function show(Request $request, string $token, MagicLinkService $links): Response
    {
        $user = $links->consume($token);

        if (! $user instanceof User) {
            // One message for unknown, expired, already-used, and
            // account-since-deleted. Distinguishing them tells whoever is
            // holding a stale link which kind of stale it is.
            return redirect()->route('login')->withErrors([
                'email' => 'That sign-in link has already been used or has expired. Ask for a new one.',
            ]);
        }

        // Session fixation: the point of a login is that the session identifier
        // afterwards is not one an attacker could have planted beforehand.
        $request->session()->regenerate();

        // ⚠️ BEFORE Auth::login(), and this route walked straight past a
        // confirmed second factor until it did. Fortify's challenge is a pipe in
        // *its own* login pipeline, which only `POST /login` runs through — so a
        // person holding mandatory 2FA could ask for a magic link instead and be
        // signed in with a single factor, on the cheapest and most prominent
        // route the login page offers. A mandatory control the sign-in page
        // itself routes around is not a control (`28` §9.1, decision 661).
        //
        // Regenerating first is deliberate: the challenge is stored in the
        // session, so it must land in the post-fixation one or it is lost.
        //
        // ⚠️ And the marking below must come BEFORE the challenge, not after
        // it. A magic-link-only user who turns on 2FA is challenged here and
        // then signed in by Fortify's own controller, which never returns to
        // this method — so marking afterwards would leave them authenticated
        // with no `auth.password_confirmed_at`, unable to reach any of the
        // endpoints that manage the factor they just used, because
        // `password.confirm` would ask them for a password they have never had.
        // That is the deadlock of decision 660 rebuilt one door further in.
        self::markInteractivelyAuthenticated($request);

        if ($challenge = SecondFactor::challenge($request, $user)) {
            return $challenge;
        }

        // last_login_at is stamped by RecordSuccessfulLogin, on the Login event
        // this fires — not here. Two of the four sign-in methods happen entirely
        // inside Fortify, where there is no controller of ours to edit.
        //
        // ⚠️ `remember: true` was unconditional here, which is half of why `28`
        // §9.1's twelve-hour ceiling could not be built (decision 670): a staff
        // session that hit any ceiling was resurrected from the cookie into a
        // fresh session, and the ceiling restarted with it. Tenants keep the
        // cookie; internal accounts do not. StaffSession::remember() is the
        // whole of that policy, and it is asked here rather than answered here.
        Auth::login($user, remember: StaffSession::remember($user));

        // ⛔ **THE SIGN-IN DESTINATION IS ONE ANSWER FOR ALL FOUR DOORS, AND
        // THIS DOOR ANSWERED IT ITSELF UNTIL 9156.** It read
        // `redirect()->intended(config('fortify.home', '/'))`, the identical
        // line `OauthLoginController` ended on — so the two ways in that are
        // *ours* never reached {@see LoginResponse} while the two that are
        // Fortify's always did. `fortify.home` is one string and there are
        // three account shapes (5490): an owner who finished onboarding was
        // sent back into the wizard by every magic-link sign-in.
        //
        // ⚠️ **THE SAME SHAPE AS THE SECOND-FACTOR DEFECT TWENTY LINES ABOVE**,
        // and 661 is the same decision: a control on the sign-in path must not
        // depend on which of the four doors was used. That one was a
        // *security* control and this one is a destination, which is why it
        // survived — nothing 403s, nothing throws, the person simply lands on
        // the wrong screen.
        //
        // The contract rather than the concrete class, so that rebinding it
        // moves all four doors at once. `intended()` lives inside it.
        return app(LoginResponseContract::class)->toResponse($request);
    }

    /**
     * Treat a completed passwordless sign-in as password confirmation.
     *
     * Not a shortcut — a necessity, and worth stating plainly because it reads
     * as a weakening. Fortify guards passkey registration with
     * `password.confirm`, which is right: a hijacked session must not be able to
     * add a permanent credential. But three of this application's four login
     * methods never establish a password, so a magic-link user has nothing to
     * confirm with, and confirming with a passkey needs the passkey they are
     * trying to create. Their first passkey would be unreachable.
     *
     * What password confirmation actually asks is "prove, again and recently,
     * that you are this person". Consuming a single-use link sent to the
     * account's own mailbox proves exactly that — it is the same factor a
     * password reset relies on, and a stronger one than a password the user
     * might have chosen badly.
     *
     * Laravel's `auth.password_timeout` bounds it, unchanged.
     */
    public static function markInteractivelyAuthenticated(Request $request): void
    {
        $request->session()->put('auth.password_confirmed_at', time());
    }
}

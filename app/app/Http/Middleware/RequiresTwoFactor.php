<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Auth\SecondFactor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `28` §9.1's *"mandatory 2FA … for every internal account"*, enforced.
 *
 * ## On the whole web group, not on the two console prefixes
 *
 * `Impersonating`'s precedent, for the same class of reason. Scoping this to
 * `/admin` and `/support` would make the rule *"an internal account without a
 * second factor cannot reach today's two console groups"*, which is a different
 * and much weaker sentence — the third group is one route-file edit away, and
 * the person adding it has no reason to think about this middleware. On the
 * whole group the rule is what §9.1 actually says, and a new internal surface
 * is covered the moment it exists.
 *
 * It costs nothing on a tenant request: `required()` is a match on an enum
 * already hydrated with the user, and a tenant never reaches the second check.
 *
 * ⚠️ **The `api` group is NOT covered, and that is a stated boundary rather than
 * an oversight.** Adding this middleware there today would mean a branch that
 * answers with a *redirect*, which is the wrong response for a JSON client.
 * **The day an internal API surface exists, this rule follows it, with a 403
 * rather than a redirect.**
 *
 * ⛔ **THE REASON GIVEN FOR THAT BOUNDARY WAS FALSE, AND THE REASON IS WHAT A
 * FUTURE READER RELIES ON — CORRECTED 2026-08-24 (9231).** This paragraph read
 * *"There is no internal API surface today: every API route here is
 * tenant-scoped, staff hold no tenant, and `Tenancy::idOrFail()` already fails
 * those requests closed."* `bootstrap/app.php` appends **only** `ResolveTenant`
 * to the `api` group, and the one authenticated route —
 * `GET /api/me`, `auth:sanctum` — calls `Tenancy::id()` and **returns 200 with
 * a body when it is null**, deliberately and with its own docblock saying so:
 * attempting a scoped read there *"would throw `TenantNotResolved`, which is the
 * correct failure for data access and the wrong response for 'tell me about
 * myself'."* So a member of staff holding a Sanctum token is answered, not
 * refused, and nothing about that is closed.
 *
 * ⚠️ **THE CONCLUSION SURVIVES ON ITS OWN MERITS AND THE MIDDLEWARE IS
 * UNCHANGED.** What `/api/me` returns to a tenantless caller is that caller's
 * own name and nothing scoped; the objection to putting a redirecting middleware
 * on a JSON group is untouched by any of this. What is removed is a sentence
 * that told the next reader the boundary was already enforced somewhere else —
 * `CLAUDE.md`'s *the paragraph explaining the hazard is what made the code
 * beside it read as considered*.
 *
 * ## ⚠️ The exemptions, and why each one is load-bearing
 *
 * A middleware that refuses *everything* refuses the enrolment screen too, and
 * an internal account would face a redirect loop with no way to satisfy it —
 * the lockout of decision 660 rebuilt deliberately instead of by accident. So
 * four things stay reachable, and the list is as short as it can be:
 *
 *   - **the enrolment screen**, which is the only way to comply;
 *   - **Fortify's 2FA management endpoints**, which are what that screen posts
 *     to — enable, confirm, the QR code, the secret key, recovery codes;
 *   - **password confirmation**, because `password.confirm` middleware guards
 *     every one of those endpoints and its own screen would otherwise be
 *     refused, which is 660's second half exactly;
 *   - **logout**, because a person who cannot comply on this device must still
 *     be able to leave. Trapping somebody in an authenticated session they can
 *     neither use nor exit is a support call by construction.
 *
 * Everything on that list either grants no authority or takes it away, and none
 * of them reads tenant data.
 *
 * ## What this does NOT do, and where that is handled
 *
 * It does not make the login itself demand a factor — a middleware runs after
 * authentication, so by the time it is asked, the session already exists.
 * **Holding a factor and having presented one are different claims**, and the
 * second is `App\Support\Auth\SecondFactor::challenge()`, wired into the two
 * login routes that sit outside Fortify's pipeline. This middleware is the half
 * that says a factor must be held at all.
 */
final class RequiresTwoFactor
{
    /**
     * Route names that stay reachable without a second factor.
     *
     * ⚠️ Names, not paths. Fortify builds its endpoints through
     * `RoutePath::for()`, so their URIs are configurable, and a path prefix here
     * would stop matching without any test failing — the enrolment screen would
     * simply become unreachable again.
     *
     * @var list<string>
     */
    private const EXEMPT = [
        'two-factor.setup',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.qr-code',
        'two-factor.secret-key',
        'two-factor.recovery-codes',
        'two-factor.regenerate-recovery-codes',
        'password.confirm',
        'password.confirm.store',
        'password.confirmation',
        'logout',
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (! SecondFactor::required($user) || SecondFactor::held($user)) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::EXEMPT, true)) {
            return $next($request);
        }

        // ⚠️ A redirect, never a 403. The person has done nothing wrong and
        // there is exactly one thing they can do about it, so sending them
        // there beats telling them they are forbidden and leaving them to find
        // the screen — which they cannot, because the console they would look
        // in is the thing being refused.
        //
        // The message travels in the session rather than being baked into the
        // screen: that screen is reachable on purpose by anyone turning 2FA on
        // voluntarily, and for them there is no banner to show.
        return redirect()->route('two-factor.setup')->with(
            'two_factor_required',
            'Two-step sign-in is required for staff accounts. Set it up here to continue.',
        );
    }
}

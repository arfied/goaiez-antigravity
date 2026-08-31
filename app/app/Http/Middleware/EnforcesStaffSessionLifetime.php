<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\StaffSessionEnd;
use App\Models\User;
use App\Support\Auth\StaffSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * `28` §9.1's *"session lifetime 12h, idle timeout 30m"*, enforced.
 *
 * `RequiresTwoFactor`'s sibling and, like it, on the **whole web group** rather
 * than on `/admin` and `/support`. Scoped to today's two console prefixes the
 * rule becomes *"a stale internal session cannot reach the screens we happen to
 * have built"*, and the third prefix is one route-file edit away from somebody
 * with no reason to think about this file. On the whole group the rule is what
 * §9.1 says, and a new internal surface is covered the moment it exists.
 *
 * It costs a tenant request one enum match: `StaffSession::applies()` reads a
 * role already hydrated with the user, and everything below it is behind that.
 *
 * ## ⚠️ It runs before `ResolveTenant`, and that is a choice
 *
 * `RequiresTwoFactor` sits *after* `Authorize` on purpose (decision 666) so that
 * a refused screen answers with the 403 that is true. The opposite is right
 * here, and the two are not in tension: 2FA enrolment is a question about a
 * session that is legitimately open, while this one is whether a session is open
 * at all. An expired session must not establish a tenant, must not have a
 * support session re-validated against it, and must not reach route model
 * binding — every one of those runs a query for a request that ended before it
 * started.
 *
 * ⚠️ **It does not repeat 666's second consequence.** Moving `RequiresTwoFactor`
 * ahead of `Authorize` would have turned twelve live `403` assertions into
 * `302`s and quietly stopped them covering authorization. This middleware
 * redirects only a session that has actually expired, and no authorization test
 * in this suite advances a clock — so those assertions keep asserting what they
 * were written for. A test pins the ordering anyway, because "nothing broke" is
 * not the same as "the order was chosen".
 *
 * ## ⚠️ No exemption list, unlike its sibling
 *
 * `RequiresTwoFactor` needs four, because a person who has not enrolled must
 * still be able to reach the screen that enrols them and the door that lets them
 * leave — refuse everything and it is decision 660's lockout rebuilt on purpose.
 * Nothing here needs one: the answer to an expired session is that the person is
 * signed out, so the enrolment screen, Fortify's endpoints and logout all reach
 * the same place they would have reached anyway. An exemption would be a route
 * an expired staff session may still use, which is a hole with no case behind it.
 */
final class EnforcesStaffSessionLifetime
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if ($end = StaffSession::expiry($request, $user)) {
            return $this->end($request, $end);
        }

        StaffSession::touch($request, $user);

        return $next($request);
    }

    /**
     * Sign them out and say why.
     *
     * A full logout rather than a redirect: `SessionGuard::logout()` is what
     * queues the recaller cookie's deletion, so ending a session without it
     * leaves the credential that reopens it sitting in the browser — which for
     * `StaffSessionEnd::Remembered` would mean refusing the same cookie on
     * every request forever, and a staff member who cannot sign in at all
     * because the cookie keeps answering ahead of the form.
     *
     * `invalidate()` then `regenerateToken()` in that order: the second mints
     * the CSRF token the sign-in form is about to post with, and doing it
     * before the flush would throw it away.
     *
     * The reason travels as `status` because that is what the sign-in screen
     * renders, and never as a validation error on `email` — nothing is wrong
     * with the address, and the screen puts field errors under the field.
     */
    private function end(Request $request, StaffSessionEnd $end): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', $end->message());
    }
}

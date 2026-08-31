<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Business;
use App\Models\ImpersonationSession;
use App\Services\Impersonation\Impersonation;
use App\Services\Tenant\TenantSuspension;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * `28` §9.5's *"login shows a plain status page with the support path"*.
 *
 * ## On the whole web group, and the direction is the point
 *
 * `RequiresTwoFactor`'s precedent, for decision 661's reason rather than for
 * symmetry. Scoping this to today's owner surfaces — `/account`, `/home`,
 * `/setup`, `/billing` — makes the rule *"a suspended owner cannot reach the
 * five screens that existed in August"*, and the sixth is one route-file edit
 * away from somebody who has no reason to think about this file. On the group,
 * a new owner surface is covered the moment it exists, and what has to be
 * thought about is the **exemption**, which is a list a reviewer can read.
 *
 * ⚠️ **THIS IS ALSO WHY IT DOES NOT DEPEND ON HOW SOMEBODY SIGNED IN.**
 * Decision 661 found three of four ways into this application never asked for a
 * second factor, because the check lived inside one login pipeline. A middleware
 * asks after authentication, so the password form, the magic link, SSO and a
 * passkey all arrive here identically — and so does a session restored from a
 * remember-me cookie, which is the door that would otherwise reopen it.
 *
 * ## ⚠️ Three exemptions, and each one is load-bearing
 *
 *   - **The status page itself**, or it is a redirect loop.
 *   - **Logout.** `RequiresTwoFactor`'s fourth exemption, for its exact reason:
 *     somebody who cannot resolve this on their own must still be able to
 *     leave, and an authenticated session a person can neither use nor exit is
 *     a support call by construction.
 *   - **The public feedback surface.** ⚠️ This is the one that would have been
 *     found in production rather than in review. `/f/{slug}` is anonymous, so
 *     `ResolveTenant` resolves nothing and the `Auth::check()` below already
 *     covers the customer at the QR code — but the tenant's **own owner**,
 *     signed in, opening their own feedback page is a real thing people do, and
 *     decision 821's whole point is that a stop does not take that page down.
 *     Named so the claim is enforced rather than incidental.
 *
 *   - ⚠️ **THE TWO EXPORT ROUTES, AND THIS IS THE ONE THAT WAS FOUND IN REVIEW
 *     RATHER THAN IN PRODUCTION** (1900). `28` §3.7 is explicit that exporting
 *     is *"never delayed, gated on retention offers, or degraded"*, and §9.5
 *     specifies the cooling window *"with export offered"* — which is a
 *     suspended or offboarding account by definition. `BuildTenantExportJob`
 *     was carefully written not to extend `AutopilotJob` for exactly that
 *     reason, and three docblocks say "NEVER GATED"; this middleware then
 *     gated the whole thing at the HTTP layer anyway, so a suspended owner
 *     could neither start an export nor fetch one already built. Decisions
 *     314–316's shape — a protection asserted in prose above a layer that
 *     contradicts it.
 *
 *     ⚠️ **`account.settings` IS DELIBERATELY *NOT* ALSO EXEMPT.** The obvious
 *     fix — let a suspended owner back onto the settings screen — hands them
 *     Pause, Resume and the review rules as well, and `28` §9.5's whole point
 *     is that a suspended account is stopped. The export moved to its own POST
 *     route so that the exemption is the width of the promise rather than the
 *     width of the page it used to live on. The on-hold page carries the
 *     control instead.
 *
 *     ⚠️ **AND THE NAME THAT "WOULD HAVE WORKED" IS `default-livewire.update`,
 *     NOT `livewire.update`** (1997) — `route:list` says
 *     `POST livewire-16868c99/update … default-livewire.update`. Four places
 *     in this slice said the latter, which resolves to nothing at all, so the
 *     "obvious" exemption somebody reaches for next would be a hole that
 *     silently does nothing rather than the dangerous one it looks like.
 *     `tests/Feature/Architecture/TenancyTest.php` now holds every name on the
 *     list below to a route that exists, so neither mistake can be added
 *     quietly.
 *
 * ⚠️ **AND ONE EXEMPTION THAT IS NOT A ROUTE: AN OPEN SUPPORT SESSION.**
 * Decision 561 makes the acting identity inside impersonation the *owner*, so
 * `ResolveTenant` resolves the suspended tenant and this middleware would show
 * the status page to the agent who is investigating the abuse report — the one
 * person who has to be able to look. Support may enter a suspended account;
 * `Impersonating` has already re-validated the session, its banner still
 * renders, and view-only mode is still read-only.
 *
 * ## What is NOT exempt, and why the list is not longer
 *
 * **`/admin` and `/support` are not on it**, and they do not need to be:
 * internal staff belong to no business (`28` §9.1), so `Tenancy::id()` is null
 * for them and the first check below passes them through. The one person this
 * would catch is decision 621's staff member who *also* owns a business, and
 * only while their own business is suspended — at which point being shown the
 * status page is correct, because the suspension is about them.
 *
 * **The `api` group is not covered**, stated rather than left silent, for
 * `RequiresTwoFactor`'s reason: this answers with a *view*, which is the wrong
 * response for a JSON client. `29` §2 rule 43's degradation belongs there
 * instead, and today the only tenant-scoped API surface is the widget feed,
 * which is a public read of already-approved reviews.
 */
final class SuspendedTenantStatus
{
    /**
     * Route names that stay reachable while a tenant is suspended.
     *
     * ⚠️ Names, not paths, on decision 667's rule. Fortify builds `logout`
     * through `RoutePath::for()`, so its URI is configurable and a path prefix
     * here would stop matching with nothing failing — leaving somebody locked
     * into a session they cannot leave.
     *
     * @var list<string>
     */
    private const EXEMPT = [
        'account.suspended',
        'logout',
        'feedback.show',
        'feedback.store',
        'feedback.thanks',
        'feedback.destination',

        // `28` §3.7's ungatable pair — starting one and fetching one. See the
        // class docblock for why `account.settings` is not here with them.
        'account.data-export.request',
        'account.data-export.download',

        // ⚠️ THE CANCELLATION PAIR (2980–2999), AND ITS REASON IS SHARPER THAN
        // THE EXPORT'S. `TenantSuspension::suspend()` writes `suspended_at`,
        // `suspended_by` and `suspension_reason` and **touches the subscription
        // not at all** — so a suspended tenant's card is still being charged
        // every cycle. Without these two names, the one population that most
        // needs to stop paying for a product it cannot use would have no way
        // to, and "you can cancel any time" would be false for them
        // specifically.
        //
        // ⚠️ EXACTLY TWO NAMES, AND `account.settings` IS STILL NOT ONE OF THEM
        // — the class docblock's rule. `/account/plan` carries the plan state
        // and the cancel control and nothing else: no Pause, no Resume, no
        // review rules, nothing that runs the product `28` §9.5 stopped.
        'account.plan',
        'account.plan.cancel',
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $businessId = Tenancy::id();

        // Nobody signed in, or signed in with no business of their own. Both
        // pass: the first is the customer at the QR code, the second is every
        // internal staff account this application has.
        if ($businessId === null || ! Auth::check()) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::EXEMPT, true)) {
            return $next($request);
        }

        // Support, inside a session they opened deliberately and which is
        // recorded. Checked before the database read below, because the
        // suspended-account case is exactly when an agent needs to be here.
        if (app(Impersonation::class)->current() instanceof ImpersonationSession) {
            return $next($request);
        }

        $business = Business::query()->find($businessId);

        if (! $business instanceof Business
            || ! app(TenantSuspension::class)->isSuspended($business)) {
            return $next($request);
        }

        // ⚠️ A REDIRECT RATHER THAN RENDERING IN PLACE, and it matters for one
        // specific reason: a suspended owner who bookmarked `/account` and hits
        // refresh should not see the status page at a URL that claims to be
        // their settings. The page has its own address, so "what is my account
        // doing" has one answer they can read, copy and send to us.
        return redirect()->route('account.suspended');
    }
}

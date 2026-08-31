<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\ImpersonationMode;
use App\Models\ImpersonationSession;
use App\Services\Impersonation\Impersonation;
use App\Support\Impersonation\ReadOnlyConnection;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Everything an impersonated request is and is not allowed to be (`28` §9.4).
 *
 * Runs **after** `ResolveTenant`, and replaces its answer rather than competing
 * with it. That ordering is not cosmetic: `ResolveTenant` resolves a tenant from
 * `businesses.owner_user_id`, and during impersonation the authenticated user
 * *is* the owner, so it resolves the right tenant on its own. This middleware's
 * tenant work is therefore a belt — what it is really here for is the three
 * things that ordering cannot give:
 *
 *   1. **The session is re-validated on every request.** The row is the
 *      authority and the session cookie is a pointer, so an expired, ended or
 *      revoked session dies here, on the next request, rather than whenever a
 *      sweep next runs. That is `28` §9.4's fourth build-failing test.
 *   2. **View mode cannot write.** Two layers, below.
 *   3. **The banner.** `28` requires it on *every* impersonated page, which
 *      means it cannot be something a view remembers to include.
 *
 * ## Why view-only takes two layers rather than one
 *
 * `28` words it as "GET-only, middleware-enforced", and the HTTP method check
 * is the first layer and the one the build-failing test names. It is not
 * sufficient here, and the reason is specific to this codebase rather than
 * theoretical: **decision 387 made a GET write on purpose.**
 * `GET /f/{slug}/to/{destination}` records a `destination_clicks` row and then
 * redirects, because a JavaScript beacon fails silently with scripting off. So
 * "GET-only" and "read-only" are different claims here, and a view-only agent
 * clicking through a customer's own feedback page would write click rows into
 * the tenant's data under the method check alone.
 *
 * The second layer is the database: the connection is put into
 * `default_transaction_read_only`, so an INSERT fails with SQLSTATE 25006 no
 * matter which verb carried the request. Same relationship as RLS to the global
 * scope — the second layer catches the day the first is wrong, and neither
 * excuses the other.
 */
final class Impersonating
{
    public function handle(Request $request, Closure $next): Response
    {
        $impersonation = app(Impersonation::class);

        // Unconditionally, before any early return, for the reason
        // ResolveTenant states about the tenant variable: the read-only flag
        // lives on a PostgreSQL *session* and outlives a request whenever a
        // connection is reused. A request that returned early without clearing
        // it would hand the next request a silently read-only connection, and
        // the symptom would be writes failing on pages nobody has touched.
        ReadOnlyConnection::clear();

        $session = $impersonation->current();

        if (! $session instanceof ImpersonationSession) {
            return $next($request);
        }

        // The tenant, restated rather than assumed. ResolveTenant has already
        // set it from the owner's own record and this must agree; if it ever
        // does not, the row wins, because the row is what somebody typed a
        // reason for.
        Tenancy::set($session->business_id);

        // ⚠️ THE ONE EXEMPTION, AND IT IS LOAD-BEARING RATHER THAN A
        // CONVENIENCE. Ending a session is a POST that writes — it stamps
        // `ended_at` and files an audit entry. Under the two view-only layers
        // below, an agent in a read-only session could not get out of it: the
        // method check would refuse the form and the read-only connection would
        // refuse the update. A support tool nobody can leave is one people
        // avoid closing properly, which turns every session into an expiry.
        //
        // Narrow on purpose — one named route, matched before either layer.
        $leaving = $request->routeIs('impersonation.stop');

        if (! $leaving) {
            if ($session->mode === ImpersonationMode::View && $this->isWrite($request)) {
                return $this->refuseWrite($request);
            }

            // Bookkeeping before the read-only flag, not after: counting the
            // page view is itself a write, and it is ours rather than the
            // tenant's. `28` §9.4 requires view-only page views be logged, so a
            // design where the read-only layer silently swallowed its own audit
            // trail would defeat the requirement it protects.
            $impersonation->recordPageView($session);

            if ($session->mode === ImpersonationMode::View) {
                ReadOnlyConnection::engage();
            }
        }

        // Also shared as a view variable, for anything that wants to render
        // differently while support is present. The banner does not depend on
        // it — see below.
        View::share('impersonationSession', $session);

        try {
            return $this->withBanner($next($request), $session);
        } finally {
            // Never leave the flag on a connection that outlives this request.
            ReadOnlyConnection::clear();
        }
    }

    /**
     * Put the banner on the page, whatever the page is.
     *
     * ⚠️ **Injected rather than included, and that is the requirement rather
     * than a shortcut.** `28` §9.4 wants the banner on *every* impersonated
     * page. This application has four hand-written layouts plus Livewire's
     * own, and adding one is ordinary work — so a per-layout `@include` is
     * always one new layout away from a page where an agent cannot tell whose
     * account they are in, and nothing would fail when that happened. Injection
     * makes the banner a property of the session instead of of the template,
     * and it covers error pages, which no layout of ours renders.
     *
     * Only HTML responses, and only ones with a `</body>` to anchor to. A JSON
     * response, a redirect, a file download and a streamed response are all
     * left untouched — appending markup to any of them would corrupt it, and
     * none of them is a page an agent is reading.
     */
    private function withBanner(Response $response, ImpersonationSession $session): Response
    {
        $content = $response->getContent();

        if ($content === false || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $anchor = strripos($content, '</body>');

        if ($anchor === false) {
            return $response;
        }

        // Two scalar lookups rather than hydrating both models. The banner needs
        // one string from each, and this is the one place in the request where
        // an exception is least acceptable — a throw here removes the banner,
        // which is the control telling an agent whose account they are in.
        $agentName = $session->agent()->value('name');
        $businessName = $session->business()->value('name');

        $banner = view('impersonation.banner', [
            'isAct' => $session->mode === ImpersonationMode::Act,
            // Both are NOT NULL foreign keys, one restricted and one cascading,
            // so neither can be absent for a row that exists. The fallbacks
            // degrade to a banner that still says support is present rather
            // than to no banner at all.
            'agentName' => is_string($agentName) ? $agentName : 'GO AI EZ support',
            'businessName' => is_string($businessName) ? $businessName : 'this account',
            'reason' => $session->reason,
            'ticketRef' => $session->ticket_ref,
            'expiresAt' => $session->expires_at->diffForHumans(),
        ])->render();

        $response->setContent(
            substr($content, 0, $anchor).$banner.substr($content, $anchor)
        );

        return $response;
    }

    /**
     * Anything that is not a read.
     *
     * HEAD and OPTIONS ride with GET because neither carries a body a handler
     * acts on, and refusing OPTIONS would break CORS preflight on any route
     * that ever gains it — a failure that would present as an unrelated
     * front-end bug.
     */
    private function isWrite(Request $request): bool
    {
        return ! in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    private function refuseWrite(Request $request): Response
    {
        $message = 'This is a read-only support session, so nothing here can be changed. '
            .'End it and start one with changes enabled if you need to fix something — '
            .'that needs a reason and a ticket.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_FORBIDDEN);
        }

        // 403 with the plain sentence rather than a redirect. `28` §9.4 asks for
        // "a friendly internal error", and a redirect back would look like the
        // action had been attempted and failed for some other reason.
        return response($message, Response::HTTP_FORBIDDEN);
    }
}

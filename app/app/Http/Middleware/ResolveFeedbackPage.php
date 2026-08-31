<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\FeedbackPage;
use App\Models\Location;
use App\Services\Feedback\FeedbackPages;
use App\Support\FeedbackRateLimits;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Establish the tenant for a public feedback page, from its slug.
 *
 * THE ONLY PLACE AN UNAUTHENTICATED REQUEST MAY SET A TENANT. That is a
 * privilege-escalation primitive by any other name, so it lives in one narrow
 * file with one input and no branches: a published slug, or a 404.
 *
 * RUNS AFTER ResolveTenant, which is why the forgetAll() below is load-bearing
 * rather than defensive. Route middleware runs after the group's, so by the time
 * this executes a signed-in visitor's own tenant and user id are already
 * established in both the context and the PostgreSQL session. Leaving either in
 * place would mean rendering tenant A's page inside a request that still
 * believes it is tenant B — and the first scoped write would land in B. Clearing
 * both makes the resolution total: either this slug establishes the tenant, or
 * no tenant exists and every scoped query fails closed.
 *
 * THE 404 DOES NOT DISTINGUISH ITS REASONS. Unknown, unpublished and
 * deleted are the same fact from a visitor's side, and telling them apart would
 * confirm that a given slug was once real. Same reasoning as
 * MarketingController::liveAudit(). A 429 in place of that 404 does not break
 * this either — see refuseOrNotFound() below — because both outcomes are still
 * exactly the same fact for every reason that failed to resolve.
 *
 * A MISS HERE COUNTS AGAINST THE VISITOR; A SUCCESSFUL RESOLUTION COSTS
 * NOTHING. This is where FeedbackRateLimits::refuseIfTooManyMisses() is
 * called, on purpose rather than from a route `throttle:` middleware —
 * whether a request is a hit or a miss is exactly what this method is in the
 * middle of deciding. Read that method's docblock for why counting misses
 * rather than requests is what keeps a shared address safe: a QR code on
 * every table puts a whole restaurant's lunch service behind one venue's
 * Wi-Fi or one mobile carrier's CGNAT, and every one of those customers
 * resolves successfully. None of them touch this limiter, however many
 * phones sit behind the same address — only a slug that fails to resolve
 * counts, and that is what an enumeration script produces almost
 * exclusively.
 */
final class ResolveFeedbackPage
{
    /**
     * Request attribute keys, so the controller and this file cannot disagree
     * about the spelling.
     */
    public const string PAGE = 'feedback_page';

    public const string LOCATION = 'feedback_location';

    public function __construct(
        private readonly FeedbackPages $pages,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Unconditionally first, before anything can short-circuit — the same
        // invariant ResolveTenant states for itself. The tenant lives in a
        // PostgreSQL *session* variable that outlives a request whenever a
        // connection is reused, so an early return without clearing would let
        // this request inherit a tenant it has no claim to.
        Tenancy::forgetAll();

        $slug = $request->route('slug');

        $page = is_string($slug) ? $this->pages->resolve($slug) : null;

        if (! $page instanceof FeedbackPage) {
            return $this->refuseOrNotFound($request);
        }

        Tenancy::set($page->business_id);

        // Read after the tenant is set, so it comes through the ordinary scoped
        // path with row-level security applying — rather than being denormalised
        // onto feedback_pages, which would put tenant data in the one table a
        // stranger can read.
        $location = Location::query()->find($page->location_id);

        if (! $location instanceof Location) {
            // The foreign key makes this unreachable in practice. It is handled
            // rather than assumed because the alternative is a null propagating
            // into a view — and because the class docblock's promise is that
            // every reason resolution can fail counts as a miss the same way.
            return $this->refuseOrNotFound($request);
        }

        $request->attributes->set(self::PAGE, $page);
        $request->attributes->set(self::LOCATION, $location);

        return $next($request);
    }

    /**
     * A miss: unknown slug, unpublished page, or (unreachably) a page with no
     * location. Every one of these is indistinguishable from a visitor's side,
     * and that stays true of what this returns too — a 429 counts the miss
     * exactly like the 404 it replaces once the visitor's budget is spent.
     */
    private function refuseOrNotFound(Request $request): Response
    {
        return FeedbackRateLimits::refuseIfTooManyMisses($request)
            ?? throw new NotFoundHttpException;
    }
}

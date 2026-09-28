<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Business;
use App\Models\BusinessMembership;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establish the tenant for a web request, from the authenticated user.
 *
 * Until this existed, `Tenancy::set()` was only ever called by tests and jobs,
 * so any web route touching tenant-owned data threw TenantNotResolved. That was
 * the correct failure — but it meant there was no working request path at all.
 *
 * Deliberately quiet when it cannot resolve. An unauthenticated request, or a
 * user with no business yet (signup incomplete), passes through with no tenant
 * established. Routes that need one then fail closed on their first query rather
 * than being redirected from here, because this middleware does not know what
 * the right destination is — that belongs with auth (FOUND-04). Aborting here
 * would also break every public route the moment it is added to the web group.
 *
 * Runs before SubstituteBindings, which is not optional: route model binding
 * resolves tenant-scoped models, and binding one before the tenant exists throws.
 *
 * Membership is the second way in, it reads its own table through member_lookup,
 * it is not a third businesses policy (decision 800), and it is one business
 * per person.
 */
final class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        // Unconditionally first, before anything can short-circuit. The tenant
        // lives in a PostgreSQL *session* variable, which outlives a request
        // whenever a connection is reused — under a pooler, or Octane. Returning
        // early without clearing would let an unauthenticated request inherit
        // the previous request's tenant, and the symptom would be data
        // appearing for someone who is not logged in at all.
        //
        // Tenancy's own docblock states the invariant this preserves: every
        // entry point sets the variable unconditionally, so a stale value is
        // always overwritten before it can be read. This is that entry point for
        // the web.
        Tenancy::forgetAll();

        $userId = Auth::id();

        if (! is_int($userId)) {
            return $next($request);
        }

        Tenancy::setUser($userId);

        // withoutGlobalScopes is load-bearing rather than lazy, and this is the
        // one place it is correct: the scope calls Tenancy::idOrFail(), which is
        // exactly what has not happened yet. Resolution is circular otherwise.
        //
        // The boundary is not dropped, only moved down a layer — the database's
        // `owner_lookup` policy still restricts this query to businesses whose
        // owner_user_id matches the session user, so it can never return someone
        // else's row no matter what this code asks for.
        // ⚠️ `oldest('id')` RATHER THAN A BARE `first()`, WHICH WAS UNORDERED
        // UNTIL 3949. `businesses.owner_user_id` carries no unique index, so
        // "one business per user" is a fact about today's data and not a
        // constraint — and an unordered `first()` under Postgres returns
        // whichever row the plan happens to reach first. The day a user owns two,
        // the tenant established for their request stops being stable between
        // requests, and since AG3 that decides whose money `/api/me` reports.
        //
        // OLDEST RATHER THAN NEWEST, DELIBERATELY. Both are deterministic; only
        // one leaves existing users where they already are. `latest('id')` would
        // silently move every affected user's tenant the moment a second business
        // was created for them, which is the same defect with a schedule.
        //
        // ⛔ THIS IS DETERMINISM, NOT A DECISION ABOUT MULTI-BUSINESS OWNERSHIP.
        // The stronger fix is a unique index on `owner_user_id`, and that is a
        // product ruling — multi-location is `locations`, not a second business —
        // which does not belong in a middleware. Recorded at 3949.
        $business = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->oldest('id')
            ->first();

        if ($business === null) {
            $membership = BusinessMembership::withoutGlobalScopes()
                ->where('user_id', $userId)
                ->whereNotNull('accepted_at')
                ->whereNull('revoked_at')
                ->first();

            if ($membership !== null) {
                Tenancy::set((int) $membership->business_id);
            }

            return $next($request);
        }

        Tenancy::set($business->id);

        return $next($request);
    }
}

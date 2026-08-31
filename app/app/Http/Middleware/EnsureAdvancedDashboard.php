<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the requesting tenant has the Advanced Dashboard enabled.
 * Spec: Doc 28 Part 1 §1.2 & BUILD-PLAN §0 / §2.13.
 */
class EnsureAdvancedDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login'));
        }

        // Check active tenant
        $businessId = Tenancy::id();
        $business = null;

        if ($businessId) {
            $business = Business::find($businessId);
        }

        if (! $business && $user instanceof User) {
            $business = $user->business;
        }

        if (! $business || ! $business->hasAdvancedDashboard()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Advanced dashboard is disabled.',
                    'message' => 'Turn on Advanced tools in Account Settings.',
                ], 403);
            }

            return redirect()->route('account.settings')->with(
                'error',
                'Advanced dashboard tools are currently disabled. Turn them on below to access power features.'
            );
        }

        return $next($request);
    }
}

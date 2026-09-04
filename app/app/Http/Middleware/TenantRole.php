<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;

class TenantRole
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

        return $next($request);
    }
}

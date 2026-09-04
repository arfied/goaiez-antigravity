<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Enums\UserRole;

class TenantRole
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);
        return $next($request);
    }
}

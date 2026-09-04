<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X192\Ui\MembershipsList;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-192')->group(function () {
    Route::get('/x-192/memberships-list', MembershipsList::class)->name('x-192.memberships-list');
});

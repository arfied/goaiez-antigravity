<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X165\Ui\Members;
use App\Modules\X165\Ui\Plans;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-165')->group(function () {
    Route::get('/x-165/plans', Plans::class)->name('x-165.plans');
    Route::get('/x-165/members', Members::class)->name('x-165.members');
});

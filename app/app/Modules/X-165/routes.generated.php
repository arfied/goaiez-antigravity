<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-165')->group(function () {
    Route::get('/x-165/plans', \App\Modules\X165\Ui\Plans::class)->name('x-165.plans');
    Route::get('/x-165/members', \App\Modules\X165\Ui\Members::class)->name('x-165.members');
});


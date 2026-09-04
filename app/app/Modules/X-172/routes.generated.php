<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-172')->group(function () {
    Route::get('/x-172/customerfacing-portal', \App\Modules\X172\Ui\CustomerfacingPortal::class)->name('x-172.customerfacing-portal');
});


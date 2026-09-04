<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-108')->group(function () {
    Route::get('/x-108/calendar', \App\Modules\X108\Ui\Calendar::class)->name('x-108.calendar');
    Route::get('/x-108/waitlist', \App\Modules\X108\Ui\Waitlist::class)->name('x-108.waitlist');
});


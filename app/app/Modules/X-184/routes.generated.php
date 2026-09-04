<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-184')->group(function () {
    Route::get('/x-184/content-week', \App\Modules\X184\Ui\ContentWeek::class)->name('x-184.content-week');
    Route::get('/x-184/calendar', \App\Modules\X184\Ui\CalendarView::class)->name('x-184.calendar');
});


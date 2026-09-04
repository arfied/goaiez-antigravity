<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X184\Ui\CalendarView;
use App\Modules\X184\Ui\ContentWeek;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-184')->group(function () {
    Route::get('/x-184/content-week', ContentWeek::class)->name('x-184.content-week');
    Route::get('/x-184/calendar', CalendarView::class)->name('x-184.calendar');
});

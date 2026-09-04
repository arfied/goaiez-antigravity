<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X108\Ui\Calendar;
use App\Modules\X108\Ui\Waitlist;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-108')->group(function () {
    Route::get('/x-108/calendar', Calendar::class)->name('x-108.calendar');
    Route::get('/x-108/waitlist', Waitlist::class)->name('x-108.waitlist');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-118')->group(function () {
    Route::get('/x-118/day-one-signup', \App\Modules\X118\Ui\DayOneSignup::class)->name('x-118.day-one-signup');
    Route::get('/x-118/groundcheck', \App\Modules\X118\Ui\Groundcheck::class)->name('x-118.groundcheck');
    Route::get('/x-118/test-call', \App\Modules\X118\Ui\TestCall::class)->name('x-118.test-call');
    Route::get('/x-118/today', \App\Modules\X118\Ui\Today::class)->name('x-118.today');
    Route::get('/x-118/same-flow', \App\Modules\X118\Ui\SameFlow::class)->name('x-118.same-flow');
    Route::get('/x-118/ttfm-distribution', \App\Modules\X118\Ui\TtfmDistribution::class)->name('x-118.ttfm-distribution');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-118')->group(function () {
    Route::get('/x-118/day-one-signup', \App\Modules\X118\Ui\DayOneSignup::class)->name('x-118.day-one-signup');
    Route::get('/x-118/groundcheck', \App\Modules\X118\Ui\Groundcheck::class)->name('x-118.groundcheck');
    Route::get('/x-118/test-call', \App\Modules\X118\Ui\TestCall::class)->name('x-118.test-call');
    Route::get('/x-118/today', \App\Modules\X118\Ui\Today::class)->name('x-118.today');
    Route::get('/x-118/same-flow', \App\Modules\X118\Ui\SameFlow::class)->name('x-118.same-flow');
    Route::get('/x-118/ttfm-distribution', \App\Modules\X118\Ui\TtfmDistribution::class)->name('x-118.ttfm-distribution');
});


<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X118\Ui\DayOneSignup;
use App\Modules\X118\Ui\Groundcheck;
use App\Modules\X118\Ui\SameFlow;
use App\Modules\X118\Ui\TestCall;
use App\Modules\X118\Ui\Today;
use App\Modules\X118\Ui\TtfmDistribution;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-118')->group(function () {
    Route::get('/x-118/day-one-signup', DayOneSignup::class)->name('x-118.day-one-signup');
    Route::get('/x-118/groundcheck', Groundcheck::class)->name('x-118.groundcheck');
    Route::get('/x-118/test-call', TestCall::class)->name('x-118.test-call');
    Route::get('/x-118/today', Today::class)->name('x-118.today');
    Route::get('/x-118/same-flow', SameFlow::class)->name('x-118.same-flow');
    Route::get('/x-118/ttfm-distribution', TtfmDistribution::class)->name('x-118.ttfm-distribution');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-118')->group(function () {
    Route::get('/x-118/day-one-signup', DayOneSignup::class)->name('x-118.day-one-signup');
    Route::get('/x-118/groundcheck', Groundcheck::class)->name('x-118.groundcheck');
    Route::get('/x-118/test-call', TestCall::class)->name('x-118.test-call');
    Route::get('/x-118/today', Today::class)->name('x-118.today');
    Route::get('/x-118/same-flow', SameFlow::class)->name('x-118.same-flow');
    Route::get('/x-118/ttfm-distribution', TtfmDistribution::class)->name('x-118.ttfm-distribution');
});

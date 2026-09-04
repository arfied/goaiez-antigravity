<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-118')->group(function () {
    Route::get('/day-one-signup', \App\Modules\X118\Ui\DayOneSignup::class)->name('x-118.day-one-signup');
    Route::get('/groundcheck', \App\Modules\X118\Ui\Groundcheck::class)->name('x-118.groundcheck');
    Route::get('/test-call', \App\Modules\X118\Ui\TestCall::class)->name('x-118.test-call');
    Route::get('/today', \App\Modules\X118\Ui\Today::class)->name('x-118.today');
    Route::get('/same-flow', \App\Modules\X118\Ui\SameFlow::class)->name('x-118.same-flow');
    Route::get('/ttfm-distribution', \App\Modules\X118\Ui\TtfmDistribution::class)->name('x-118.ttfm-distribution');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-118')->group(function () {
    Route::get('/day-one-signup', \App\Modules\X118\Ui\DayOneSignup::class)->name('x-118.day-one-signup.admin');
    Route::get('/groundcheck', \App\Modules\X118\Ui\Groundcheck::class)->name('x-118.groundcheck.admin');
    Route::get('/test-call', \App\Modules\X118\Ui\TestCall::class)->name('x-118.test-call.admin');
    Route::get('/today', \App\Modules\X118\Ui\Today::class)->name('x-118.today.admin');
    Route::get('/same-flow', \App\Modules\X118\Ui\SameFlow::class)->name('x-118.same-flow.admin');
    Route::get('/ttfm-distribution', \App\Modules\X118\Ui\TtfmDistribution::class)->name('x-118.ttfm-distribution.admin');
});


<?php

declare(strict_types=1);

use App\Modules\X118\Ui\DayOneSignup;
use App\Modules\X118\Ui\Groundcheck;
use App\Modules\X118\Ui\SameFlow;
use App\Modules\X118\Ui\TestCall;
use App\Modules\X118\Ui\Today;
use App\Modules\X118\Ui\TtfmDistribution;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-118')->group(function () {
    Route::get('/day-one-signup', DayOneSignup::class)->name('x-118.day-one-signup');
    Route::get('/day-one-signup', DayOneSignup::class)->name('x-118.day-one-signup');
    Route::get('/groundcheck', Groundcheck::class)->name('x-118.groundcheck');
    Route::get('/groundcheck', Groundcheck::class)->name('x-118.groundcheck');
    Route::get('/test-call', TestCall::class)->name('x-118.test-call');
    Route::get('/test-call', TestCall::class)->name('x-118.test-call');
    Route::get('/today', Today::class)->name('x-118.today');
    Route::get('/today', Today::class)->name('x-118.today');
    Route::get('/same-flow', SameFlow::class)->name('x-118.same-flow');
    Route::get('/same-flow', SameFlow::class)->name('x-118.same-flow');
    Route::get('/ttfm-distribution', TtfmDistribution::class)->name('x-118.ttfm-distribution');
    Route::get('/ttfm-distribution', TtfmDistribution::class)->name('x-118.ttfm-distribution');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-118')->group(function () {
    Route::get('/day-one-signup', DayOneSignup::class)->name('x-118.day-one-signup.admin');
    Route::get('/groundcheck', Groundcheck::class)->name('x-118.groundcheck.admin');
    Route::get('/test-call', TestCall::class)->name('x-118.test-call.admin');
    Route::get('/today', Today::class)->name('x-118.today.admin');
    Route::get('/same-flow', SameFlow::class)->name('x-118.same-flow.admin');
    Route::get('/ttfm-distribution', TtfmDistribution::class)->name('x-118.ttfm-distribution.admin');
});

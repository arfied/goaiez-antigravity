<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-sms')->group(function () {
    Route::get('/c-sms/thread', \App\Modules\CSms\Ui\Thread::class)->name('c-sms.thread');
    Route::get('/c-sms/composer-segment-warning', \App\Modules\CSms\Ui\ComposerSegmentWarning::class)->name('c-sms.composer-segment-warning');
    Route::get('/c-sms/donottext-list', \App\Modules\CSms\Ui\DonottextList::class)->name('c-sms.donottext-list');
    Route::get('/c-sms/pernumber-complaint-monitoring', \App\Modules\CSms\Ui\PernumberComplaintMonitoring::class)->name('c-sms.pernumber-complaint-monitoring');
});


<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\CSms\Ui\ComposerSegmentWarning;
use App\Modules\CSms\Ui\DonottextList;
use App\Modules\CSms\Ui\PernumberComplaintMonitoring;
use App\Modules\CSms\Ui\Thread;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-sms')->group(function () {
    Route::get('/c-sms/thread', Thread::class)->name('c-sms.thread');
    Route::get('/c-sms/composer-segment-warning', ComposerSegmentWarning::class)->name('c-sms.composer-segment-warning');
    Route::get('/c-sms/donottext-list', DonottextList::class)->name('c-sms.donottext-list');
    Route::get('/c-sms/pernumber-complaint-monitoring', PernumberComplaintMonitoring::class)->name('c-sms.pernumber-complaint-monitoring');
});

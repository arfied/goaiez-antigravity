<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-telephony')->group(function () {
    Route::get('/c-telephony/carrier-roster-health', \App\Modules\CTelephony\Ui\CarrierRosterHealth::class)->name('c-telephony.carrier-roster-health');
    Route::get('/c-telephony/failover-log', \App\Modules\CTelephony\Ui\FailoverLog::class)->name('c-telephony.failover-log');
});


<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\CTelephony\Ui\CarrierRosterHealth;
use App\Modules\CTelephony\Ui\FailoverLog;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-telephony')->group(function () {
    Route::get('/c-telephony/carrier-roster-health', CarrierRosterHealth::class)->name('c-telephony.carrier-roster-health');
    Route::get('/c-telephony/failover-log', FailoverLog::class)->name('c-telephony.failover-log');
});

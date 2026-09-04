<?php

declare(strict_types=1);

use App\Modules\CTelephony\Ui\CarrierRosterHealth;
use App\Modules\CTelephony\Ui\FailoverLog;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-telephony')->group(function () {
    Route::get('/carrier-roster-health', CarrierRosterHealth::class)->name('c-telephony.carrier-roster-health');
    Route::get('/failover-log', FailoverLog::class)->name('c-telephony.failover-log');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-telephony')->group(function () {
    Route::get('/carrier-roster-health', \App\Modules\CTelephony\Ui\CarrierRosterHealth::class)->name('c-telephony.carrier-roster-health');
    Route::get('/failover-log', \App\Modules\CTelephony\Ui\FailoverLog::class)->name('c-telephony.failover-log');
});


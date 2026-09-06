<?php

declare(strict_types=1);

use App\Modules\X203\Ui\DrDashboard;
use App\Modules\X203\Ui\RestorationtestLog;
use App\Modules\X203\Ui\RunbookRunner;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-203')->group(function () {
    Route::get('/dr-dashboard', DrDashboard::class)->name('x-203.dr-dashboard');
    Route::get('/restorationtest-log', RestorationtestLog::class)->name('x-203.restorationtest-log');
    Route::get('/runbook-runner', RunbookRunner::class)->name('x-203.runbook-runner');
});

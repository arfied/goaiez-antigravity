<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-127')->group(function () {
    Route::get('/tenantzeroconsole', \App\Modules\X127\Ui\TenantZeroConsole::class)->name('x-127.tenant-zero-console');
    Route::get('/metricproofpanel', \App\Modules\X127\Ui\MetricProofPanel::class)->name('x-127.metric-proof-panel');
});


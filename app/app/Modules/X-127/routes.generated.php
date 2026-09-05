<?php

declare(strict_types=1);

use App\Modules\X127\Ui\MetricProofPanel;
use App\Modules\X127\Ui\TenantZeroConsole;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-127')->group(function () {
    Route::get('/tenantzeroconsole', TenantZeroConsole::class)->name('x-127.tenant-zero-console');
    Route::get('/metricproofpanel', MetricProofPanel::class)->name('x-127.metric-proof-panel');
});

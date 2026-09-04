<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-127')->group(function () {
    Route::get('/x-127/tenantzeroconsole', \App\Modules\X127\Ui\TenantZeroConsole::class)->name('x-127.tenant-zero-console');
    Route::get('/x-127/metricproofpanel', \App\Modules\X127\Ui\MetricProofPanel::class)->name('x-127.metric-proof-panel');
});


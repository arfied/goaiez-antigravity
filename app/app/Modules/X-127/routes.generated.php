<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X127\Ui\MetricProofPanel;
use App\Modules\X127\Ui\TenantZeroConsole;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-127')->group(function () {
    Route::get('/x-127/tenantzeroconsole', TenantZeroConsole::class)->name('x-127.tenant-zero-console');
    Route::get('/x-127/metricproofpanel', MetricProofPanel::class)->name('x-127.metric-proof-panel');
});

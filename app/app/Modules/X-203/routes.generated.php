<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-203')->group(function () {
    Route::get('/x-203/dr-dashboard', \App\Modules\X203\Ui\DrDashboard::class)->name('x-203.dr-dashboard');
    Route::get('/x-203/restorationtest-log', \App\Modules\X203\Ui\RestorationtestLog::class)->name('x-203.restorationtest-log');
    Route::get('/x-203/runbook-runner', \App\Modules\X203\Ui\RunbookRunner::class)->name('x-203.runbook-runner');
});


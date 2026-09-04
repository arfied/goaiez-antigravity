<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

if (! class_exists('TenantRoleMiddleware')) {
    class TenantRoleMiddleware
    {
        public function handle($request, $next) {
            abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
            return $next($request);
        }
    }
}

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-203')->group(function () {
    Route::get('/dr-dashboard', \App\Modules\X203\Ui\DrDashboard::class)->name('x-203.dr-dashboard');
    Route::get('/restorationtest-log', \App\Modules\X203\Ui\RestorationtestLog::class)->name('x-203.restorationtest-log');
    Route::get('/runbook-runner', \App\Modules\X203\Ui\RunbookRunner::class)->name('x-203.runbook-runner');
});


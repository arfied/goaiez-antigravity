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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/c-billing')->group(function () {
    Route::get('/credits', \App\Modules\CBilling\Ui\Credits::class)->name('c-billing.credits');
    Route::get('/mrr', \App\Modules\CBilling\Ui\Mrr::class)->name('c-billing.mrr');
    Route::get('/revenue-recovery', \App\Modules\CBilling\Ui\RevenueRecovery::class)->name('c-billing.revenue-recovery');
    Route::get('/dunning-board', \App\Modules\CBilling\Ui\DunningBoard::class)->name('c-billing.dunning-board');
});


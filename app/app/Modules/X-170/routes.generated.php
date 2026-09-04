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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-170')->group(function () {
    Route::get('/commissions', \App\Modules\X170\Ui\Commissions::class)->name('x-170.commissions');
    Route::get('/scorecard', \App\Modules\X170\Ui\ScorecardUi::class)->name('x-170.scorecard');
});


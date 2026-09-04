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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-205')->group(function () {
    Route::get('/portal', \App\Modules\X205\Ui\AffiliatePortal::class)->name('x-205.portal');
    Route::get('/earnings', \App\Modules\X205\Ui\EarningsView::class)->name('x-205.earnings');
    Route::get('/payout-run', \App\Modules\X205\Ui\PayoutRunView::class)->name('x-205.payout-run');
});


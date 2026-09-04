<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X205\Ui\AffiliatePortal;
use App\Modules\X205\Ui\EarningsView;
use App\Modules\X205\Ui\PayoutRunView;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-205')->group(function () {
    Route::get('/x-205/portal', AffiliatePortal::class)->name('x-205.portal');
    Route::get('/x-205/earnings', EarningsView::class)->name('x-205.earnings');
    Route::get('/x-205/payout-run', PayoutRunView::class)->name('x-205.payout-run');
});

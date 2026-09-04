<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X163\Ui\ConfirmationScreen;
use App\Modules\X163\Ui\DailyPricingDigest;
use App\Modules\X163\Ui\Pricebook;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-163')->group(function () {
    Route::get('/x-163/pricebook', Pricebook::class)->name('x-163.pricebook');
    Route::get('/x-163/confirmation-screen', ConfirmationScreen::class)->name('x-163.confirmation-screen');
    Route::get('/x-163/daily-pricing-digest', DailyPricingDigest::class)->name('x-163.daily-pricing-digest');
});

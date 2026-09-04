<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-163')->group(function () {
    Route::get('/x-163/pricebook', \App\Modules\X163\Ui\Pricebook::class)->name('x-163.pricebook');
    Route::get('/x-163/confirmation-screen', \App\Modules\X163\Ui\ConfirmationScreen::class)->name('x-163.confirmation-screen');
    Route::get('/x-163/daily-pricing-digest', \App\Modules\X163\Ui\DailyPricingDigest::class)->name('x-163.daily-pricing-digest');
});


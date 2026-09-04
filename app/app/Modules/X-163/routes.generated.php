<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-163')->group(function () {
    Route::get('/pricebook', \App\Modules\X163\Ui\Pricebook::class)->name('x-163.pricebook');
    Route::get('/confirmation-screen', \App\Modules\X163\Ui\ConfirmationScreen::class)->name('x-163.confirmation-screen');
    Route::get('/daily-pricing-digest', \App\Modules\X163\Ui\DailyPricingDigest::class)->name('x-163.daily-pricing-digest');
});


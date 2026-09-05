<?php

declare(strict_types=1);

use App\Modules\X163\Ui\ConfirmationScreen;
use App\Modules\X163\Ui\DailyPricingDigest;
use App\Modules\X163\Ui\Pricebook;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-163')->group(function () {
    Route::get('/pricebook', Pricebook::class)->name('x-163.pricebook');
    Route::get('/confirmation-screen', ConfirmationScreen::class)->name('x-163.confirmation-screen');
    Route::get('/daily-pricing-digest', DailyPricingDigest::class)->name('x-163.daily-pricing-digest');
});

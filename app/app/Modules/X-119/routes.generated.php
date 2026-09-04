<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-119')->group(function () {
    Route::get('/reviewwhatifound-screen', \App\Modules\X119\Ui\ReviewwhatifoundScreen::class)->name('x-119.reviewwhatifound-screen');
    Route::get('/price-confirmation-screen', \App\Modules\X119\Ui\PriceConfirmationScreen::class)->name('x-119.price-confirmation-screen');
    Route::get('/teaching-box', \App\Modules\X119\Ui\TeachingBox::class)->name('x-119.teaching-box');
    Route::get('/fact-freshness-per', \App\Modules\X119\Ui\FactFreshnessPer::class)->name('x-119.fact-freshness-per');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-119')->group(function () {
    Route::get('/reviewwhatifound-screen', \App\Modules\X119\Ui\ReviewwhatifoundScreen::class)->name('x-119.reviewwhatifound-screen.admin');
    Route::get('/price-confirmation-screen', \App\Modules\X119\Ui\PriceConfirmationScreen::class)->name('x-119.price-confirmation-screen.admin');
    Route::get('/teaching-box', \App\Modules\X119\Ui\TeachingBox::class)->name('x-119.teaching-box.admin');
    Route::get('/fact-freshness-per', \App\Modules\X119\Ui\FactFreshnessPer::class)->name('x-119.fact-freshness-per.admin');
});


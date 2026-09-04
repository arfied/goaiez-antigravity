<?php

declare(strict_types=1);

use App\Modules\X210\Ui\ActivePromotions;
use App\Modules\X210\Ui\EarnedVsGivenPanel;
use App\Modules\X210\Ui\PromotionBuilder;
use App\Modules\X210\Ui\RedemptionsList;
use App\Modules\X210\Ui\TargetingPreview;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-210')->group(function () {
    Route::get('/promotion-builder', PromotionBuilder::class)->name('x-210.promotion-builder');
    Route::get('/active-promotions', ActivePromotions::class)->name('x-210.active-promotions');
    Route::get('/redemptions', RedemptionsList::class)->name('x-210.redemptions');
    Route::get('/earnedvsgiven-panel', EarnedVsGivenPanel::class)->name('x-210.earnedvsgiven-panel');
    Route::get('/targeting-preview', TargetingPreview::class)->name('x-210.targeting-preview');
});

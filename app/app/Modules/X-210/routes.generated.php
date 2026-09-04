<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X210\Ui\ActivePromotions;
use App\Modules\X210\Ui\EarnedVsGivenPanel;
use App\Modules\X210\Ui\PromotionBuilder;
use App\Modules\X210\Ui\RedemptionsList;
use App\Modules\X210\Ui\TargetingPreview;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-210')->group(function () {
    Route::get('/x-210/promotion-builder', PromotionBuilder::class)->name('x-210.promotion-builder');
    Route::get('/x-210/active-promotions', ActivePromotions::class)->name('x-210.active-promotions');
    Route::get('/x-210/redemptions', RedemptionsList::class)->name('x-210.redemptions');
    Route::get('/x-210/earnedvsgiven-panel', EarnedVsGivenPanel::class)->name('x-210.earnedvsgiven-panel');
    Route::get('/x-210/targeting-preview', TargetingPreview::class)->name('x-210.targeting-preview');
});

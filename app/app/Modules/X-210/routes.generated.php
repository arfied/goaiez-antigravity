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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-210')->group(function () {
    Route::get('/promotion-builder', \App\Modules\X210\Ui\PromotionBuilder::class)->name('x-210.promotion-builder');
    Route::get('/active-promotions', \App\Modules\X210\Ui\ActivePromotions::class)->name('x-210.active-promotions');
    Route::get('/redemptions', \App\Modules\X210\Ui\RedemptionsList::class)->name('x-210.redemptions');
    Route::get('/earnedvsgiven-panel', \App\Modules\X210\Ui\EarnedVsGivenPanel::class)->name('x-210.earnedvsgiven-panel');
    Route::get('/targeting-preview', \App\Modules\X210\Ui\TargetingPreview::class)->name('x-210.targeting-preview');
});


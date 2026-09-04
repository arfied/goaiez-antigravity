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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-119')->group(function () {
    Route::get('/x-119/reviewwhatifound-screen', \App\Modules\X119\Ui\ReviewwhatifoundScreen::class)->name('x-119.reviewwhatifound-screen');
    Route::get('/x-119/price-confirmation-screen', \App\Modules\X119\Ui\PriceConfirmationScreen::class)->name('x-119.price-confirmation-screen');
    Route::get('/x-119/teaching-box', \App\Modules\X119\Ui\TeachingBox::class)->name('x-119.teaching-box');
    Route::get('/x-119/fact-freshness-per', \App\Modules\X119\Ui\FactFreshnessPer::class)->name('x-119.fact-freshness-per');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-119')->group(function () {
    Route::get('/x-119/reviewwhatifound-screen', \App\Modules\X119\Ui\ReviewwhatifoundScreen::class)->name('x-119.reviewwhatifound-screen');
    Route::get('/x-119/price-confirmation-screen', \App\Modules\X119\Ui\PriceConfirmationScreen::class)->name('x-119.price-confirmation-screen');
    Route::get('/x-119/teaching-box', \App\Modules\X119\Ui\TeachingBox::class)->name('x-119.teaching-box');
    Route::get('/x-119/fact-freshness-per', \App\Modules\X119\Ui\FactFreshnessPer::class)->name('x-119.fact-freshness-per');
});


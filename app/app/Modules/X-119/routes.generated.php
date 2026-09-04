<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X119\Ui\FactFreshnessPer;
use App\Modules\X119\Ui\PriceConfirmationScreen;
use App\Modules\X119\Ui\ReviewwhatifoundScreen;
use App\Modules\X119\Ui\TeachingBox;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-119')->group(function () {
    Route::get('/x-119/reviewwhatifound-screen', ReviewwhatifoundScreen::class)->name('x-119.reviewwhatifound-screen');
    Route::get('/x-119/price-confirmation-screen', PriceConfirmationScreen::class)->name('x-119.price-confirmation-screen');
    Route::get('/x-119/teaching-box', TeachingBox::class)->name('x-119.teaching-box');
    Route::get('/x-119/fact-freshness-per', FactFreshnessPer::class)->name('x-119.fact-freshness-per');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-119')->group(function () {
    Route::get('/x-119/reviewwhatifound-screen', ReviewwhatifoundScreen::class)->name('x-119.reviewwhatifound-screen');
    Route::get('/x-119/price-confirmation-screen', PriceConfirmationScreen::class)->name('x-119.price-confirmation-screen');
    Route::get('/x-119/teaching-box', TeachingBox::class)->name('x-119.teaching-box');
    Route::get('/x-119/fact-freshness-per', FactFreshnessPer::class)->name('x-119.fact-freshness-per');
});

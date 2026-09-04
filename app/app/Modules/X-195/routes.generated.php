<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X195\Ui\ManifestReviewQueueView;
use App\Modules\X195\Ui\MarketplaceView;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-195')->group(function () {
    Route::get('/x-195/marketplace', MarketplaceView::class)->name('x-195.marketplace');
    Route::get('/x-195/manifest-review-queue', ManifestReviewQueueView::class)->name('x-195.manifest-review-queue');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-195')->group(function () {
    Route::get('/x-195/marketplace', MarketplaceView::class)->name('x-195.marketplace');
    Route::get('/x-195/manifest-review-queue', ManifestReviewQueueView::class)->name('x-195.manifest-review-queue');
});

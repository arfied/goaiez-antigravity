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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-195')->group(function () {
    Route::get('/x-195/marketplace', \App\Modules\X195\Ui\MarketplaceView::class)->name('x-195.marketplace');
    Route::get('/x-195/manifest-review-queue', \App\Modules\X195\Ui\ManifestReviewQueueView::class)->name('x-195.manifest-review-queue');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-195')->group(function () {
    Route::get('/x-195/marketplace', \App\Modules\X195\Ui\MarketplaceView::class)->name('x-195.marketplace');
    Route::get('/x-195/manifest-review-queue', \App\Modules\X195\Ui\ManifestReviewQueueView::class)->name('x-195.manifest-review-queue');
});


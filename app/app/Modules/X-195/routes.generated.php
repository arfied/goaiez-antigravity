<?php

declare(strict_types=1);

use App\Modules\X195\Ui\ManifestReviewQueueView;
use App\Modules\X195\Ui\MarketplaceView;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-195')->group(function () {
    Route::get('/marketplace', MarketplaceView::class)->name('x-195.marketplace');
    Route::get('/manifest-review-queue', ManifestReviewQueueView::class)->name('x-195.manifest-review-queue');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-195')->group(function () {
    Route::get('/marketplace', MarketplaceView::class)->name('x-195.marketplace.admin');
    Route::get('/manifest-review-queue', ManifestReviewQueueView::class)->name('x-195.manifest-review-queue.admin');
});

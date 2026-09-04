<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-195')->group(function () {
    Route::get('/marketplace', \App\Modules\X195\Ui\MarketplaceView::class)->name('x-195.marketplace');
    Route::get('/manifest-review-queue', \App\Modules\X195\Ui\ManifestReviewQueueView::class)->name('x-195.manifest-review-queue');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-195')->group(function () {
    Route::get('/marketplace', \App\Modules\X195\Ui\MarketplaceView::class)->name('x-195.marketplace.admin');
    Route::get('/manifest-review-queue', \App\Modules\X195\Ui\ManifestReviewQueueView::class)->name('x-195.manifest-review-queue.admin');
});


<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-173')->group(function () {
    Route::get('/connection-mapping', \App\Modules\X173\Ui\ConnectionMappingView::class)->name('x-173.connection-mapping');
    Route::get('/conflicts-list', \App\Modules\X173\Ui\ConflictsListView::class)->name('x-173.conflicts-list');
    Route::get('/sync-error-rate', \App\Modules\X173\Ui\SyncErrorRateView::class)->name('x-173.sync-error-rate');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-173')->group(function () {
    Route::get('/connection-mapping', \App\Modules\X173\Ui\ConnectionMappingView::class)->name('x-173.connection-mapping.admin');
    Route::get('/conflicts-list', \App\Modules\X173\Ui\ConflictsListView::class)->name('x-173.conflicts-list.admin');
    Route::get('/sync-error-rate', \App\Modules\X173\Ui\SyncErrorRateView::class)->name('x-173.sync-error-rate.admin');
});


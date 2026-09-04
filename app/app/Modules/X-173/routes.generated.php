<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-173')->group(function () {
    Route::get('/x-173/connection-mapping', \App\Modules\X173\Ui\ConnectionMappingView::class)->name('x-173.connection-mapping');
    Route::get('/x-173/conflicts-list', \App\Modules\X173\Ui\ConflictsListView::class)->name('x-173.conflicts-list');
    Route::get('/x-173/sync-error-rate', \App\Modules\X173\Ui\SyncErrorRateView::class)->name('x-173.sync-error-rate');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-173')->group(function () {
    Route::get('/x-173/connection-mapping', \App\Modules\X173\Ui\ConnectionMappingView::class)->name('x-173.connection-mapping');
    Route::get('/x-173/conflicts-list', \App\Modules\X173\Ui\ConflictsListView::class)->name('x-173.conflicts-list');
    Route::get('/x-173/sync-error-rate', \App\Modules\X173\Ui\SyncErrorRateView::class)->name('x-173.sync-error-rate');
});


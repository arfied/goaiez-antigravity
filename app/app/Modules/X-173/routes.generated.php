<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X173\Ui\ConflictsListView;
use App\Modules\X173\Ui\ConnectionMappingView;
use App\Modules\X173\Ui\SyncErrorRateView;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-173')->group(function () {
    Route::get('/x-173/connection-mapping', ConnectionMappingView::class)->name('x-173.connection-mapping');
    Route::get('/x-173/conflicts-list', ConflictsListView::class)->name('x-173.conflicts-list');
    Route::get('/x-173/sync-error-rate', SyncErrorRateView::class)->name('x-173.sync-error-rate');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-173')->group(function () {
    Route::get('/x-173/connection-mapping', ConnectionMappingView::class)->name('x-173.connection-mapping');
    Route::get('/x-173/conflicts-list', ConflictsListView::class)->name('x-173.conflicts-list');
    Route::get('/x-173/sync-error-rate', SyncErrorRateView::class)->name('x-173.sync-error-rate');
});

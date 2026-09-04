<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X156\Ui\ConnectSourceView;
use App\Modules\X156\Ui\IngestVolumeByView;
use App\Modules\X156\Ui\RejectedrowsListView;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-156')->group(function () {
    Route::get('/x-156/connect-source', ConnectSourceView::class)->name('x-156.connect-source');
    Route::get('/x-156/rejectedrows-list', RejectedrowsListView::class)->name('x-156.rejectedrows-list');
    Route::get('/x-156/ingest-volume-by', IngestVolumeByView::class)->name('x-156.ingest-volume-by');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-156')->group(function () {
    Route::get('/x-156/connect-source', ConnectSourceView::class)->name('x-156.connect-source');
    Route::get('/x-156/rejectedrows-list', RejectedrowsListView::class)->name('x-156.rejectedrows-list');
    Route::get('/x-156/ingest-volume-by', IngestVolumeByView::class)->name('x-156.ingest-volume-by');
});

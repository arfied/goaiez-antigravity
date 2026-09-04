<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-156')->group(function () {
    Route::get('/x-156/connect-source', \App\Modules\X156\Ui\ConnectSourceView::class)->name('x-156.connect-source');
    Route::get('/x-156/rejectedrows-list', \App\Modules\X156\Ui\RejectedrowsListView::class)->name('x-156.rejectedrows-list');
    Route::get('/x-156/ingest-volume-by', \App\Modules\X156\Ui\IngestVolumeByView::class)->name('x-156.ingest-volume-by');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-156')->group(function () {
    Route::get('/x-156/connect-source', \App\Modules\X156\Ui\ConnectSourceView::class)->name('x-156.connect-source');
    Route::get('/x-156/rejectedrows-list', \App\Modules\X156\Ui\RejectedrowsListView::class)->name('x-156.rejectedrows-list');
    Route::get('/x-156/ingest-volume-by', \App\Modules\X156\Ui\IngestVolumeByView::class)->name('x-156.ingest-volume-by');
});


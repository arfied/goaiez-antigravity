<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-212')->group(function () {
    Route::get('/x-212/pick-source', \App\Modules\X212\Ui\PickSource::class)->name('x-212.pick-source');
    Route::get('/x-212/unmatchedfield-map', \App\Modules\X212\Ui\UnmatchedfieldMap::class)->name('x-212.unmatchedfield-map');
    Route::get('/x-212/dryrun-preview', \App\Modules\X212\Ui\DryrunPreview::class)->name('x-212.dryrun-preview');
    Route::get('/x-212/reconciliation-report', \App\Modules\X212\Ui\ReconciliationReport::class)->name('x-212.reconciliation-report');
    Route::get('/x-212/commit', \App\Modules\X212\Ui\Commit::class)->name('x-212.commit');
    Route::get('/x-212/postimport-audit', \App\Modules\X212\Ui\PostimportAudit::class)->name('x-212.postimport-audit');
});


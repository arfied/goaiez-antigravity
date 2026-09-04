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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-212')->group(function () {
    Route::get('/pick-source', \App\Modules\X212\Ui\PickSource::class)->name('x-212.pick-source');
    Route::get('/unmatchedfield-map', \App\Modules\X212\Ui\UnmatchedfieldMap::class)->name('x-212.unmatchedfield-map');
    Route::get('/dryrun-preview', \App\Modules\X212\Ui\DryrunPreview::class)->name('x-212.dryrun-preview');
    Route::get('/reconciliation-report', \App\Modules\X212\Ui\ReconciliationReport::class)->name('x-212.reconciliation-report');
    Route::get('/commit', \App\Modules\X212\Ui\Commit::class)->name('x-212.commit');
    Route::get('/postimport-audit', \App\Modules\X212\Ui\PostimportAudit::class)->name('x-212.postimport-audit');
});


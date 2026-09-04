<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X212\Ui\Commit;
use App\Modules\X212\Ui\DryrunPreview;
use App\Modules\X212\Ui\PickSource;
use App\Modules\X212\Ui\PostimportAudit;
use App\Modules\X212\Ui\ReconciliationReport;
use App\Modules\X212\Ui\UnmatchedfieldMap;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-212')->group(function () {
    Route::get('/x-212/pick-source', PickSource::class)->name('x-212.pick-source');
    Route::get('/x-212/unmatchedfield-map', UnmatchedfieldMap::class)->name('x-212.unmatchedfield-map');
    Route::get('/x-212/dryrun-preview', DryrunPreview::class)->name('x-212.dryrun-preview');
    Route::get('/x-212/reconciliation-report', ReconciliationReport::class)->name('x-212.reconciliation-report');
    Route::get('/x-212/commit', Commit::class)->name('x-212.commit');
    Route::get('/x-212/postimport-audit', PostimportAudit::class)->name('x-212.postimport-audit');
});

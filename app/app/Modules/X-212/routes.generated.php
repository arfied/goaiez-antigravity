<?php

declare(strict_types=1);

use App\Modules\X212\Ui\Commit;
use App\Modules\X212\Ui\DryrunPreview;
use App\Modules\X212\Ui\PickSource;
use App\Modules\X212\Ui\PostimportAudit;
use App\Modules\X212\Ui\ReconciliationReport;
use App\Modules\X212\Ui\UnmatchedfieldMap;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-212')->group(function () {
    Route::get('/pick-source', PickSource::class)->name('x-212.pick-source');
    Route::get('/unmatchedfield-map', UnmatchedfieldMap::class)->name('x-212.unmatchedfield-map');
    Route::get('/dryrun-preview', DryrunPreview::class)->name('x-212.dryrun-preview');
    Route::get('/reconciliation-report', ReconciliationReport::class)->name('x-212.reconciliation-report');
    Route::get('/commit', Commit::class)->name('x-212.commit');
    Route::get('/postimport-audit', PostimportAudit::class)->name('x-212.postimport-audit');
});

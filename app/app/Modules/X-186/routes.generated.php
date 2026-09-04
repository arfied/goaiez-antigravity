<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-186')->group(function () {
    Route::get('/sequence-builder', \App\Modules\X186\Ui\SequenceBuilder::class)->name('x-186.sequence-builder');
    Route::get('/audience-preview-count', \App\Modules\X186\Ui\AudiencePreviewCount::class)->name('x-186.audience-preview-count');
    Route::get('/live-run', \App\Modules\X186\Ui\LiveRun::class)->name('x-186.live-run');
    Route::get('/stop-log', \App\Modules\X186\Ui\StopLog::class)->name('x-186.stop-log');
});


<?php

declare(strict_types=1);

use App\Modules\X186\Ui\AudiencePreviewCount;
use App\Modules\X186\Ui\LiveRun;
use App\Modules\X186\Ui\SequenceBuilder;
use App\Modules\X186\Ui\StopLog;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-186')->group(function () {
    Route::get('/sequence-builder', SequenceBuilder::class)->name('x-186.sequence-builder');
    Route::get('/audience-preview-count', AudiencePreviewCount::class)->name('x-186.audience-preview-count');
    Route::get('/live-run', LiveRun::class)->name('x-186.live-run');
    Route::get('/stop-log', StopLog::class)->name('x-186.stop-log');
});

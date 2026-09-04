<?php

declare(strict_types=1);

use App\Modules\X166\Ui\ByService;
use App\Modules\X166\Ui\BySource;
use App\Modules\X166\Ui\ByTech;
use App\Modules\X166\Ui\MarginByJob;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-166')->group(function () {
    Route::get('/margin-by-job', MarginByJob::class)->name('x-166.margin-by-job');
    Route::get('/by-tech', ByTech::class)->name('x-166.by-tech');
    Route::get('/by-service', ByService::class)->name('x-166.by-service');
    Route::get('/by-source', BySource::class)->name('x-166.by-source');
});

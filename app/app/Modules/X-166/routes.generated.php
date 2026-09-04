<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-166')->group(function () {
    Route::get('/margin-by-job', \App\Modules\X166\Ui\MarginByJob::class)->name('x-166.margin-by-job');
    Route::get('/by-tech', \App\Modules\X166\Ui\ByTech::class)->name('x-166.by-tech');
    Route::get('/by-service', \App\Modules\X166\Ui\ByService::class)->name('x-166.by-service');
    Route::get('/by-source', \App\Modules\X166\Ui\BySource::class)->name('x-166.by-source');
});


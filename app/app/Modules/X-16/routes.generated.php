<?php

declare(strict_types=1);

use App\Modules\X16\Ui\GeogridMap;
use App\Modules\X16\Ui\HarvestCoverageBy;
use App\Modules\X16\Ui\ServiceareaPolygon;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-16')->group(function () {
    Route::get('/geogrid-map', GeogridMap::class)->name('x-16.geogrid-map');
    Route::get('/servicearea-polygon', ServiceareaPolygon::class)->name('x-16.servicearea-polygon');
    Route::get('/harvest-coverage-by', HarvestCoverageBy::class)->name('x-16.harvest-coverage-by');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-16')->group(function () {
    Route::get('/geogrid-map', GeogridMap::class)->name('x-16.geogrid-map.admin');
    Route::get('/servicearea-polygon', ServiceareaPolygon::class)->name('x-16.servicearea-polygon.admin');
    Route::get('/harvest-coverage-by', HarvestCoverageBy::class)->name('x-16.harvest-coverage-by.admin');
});

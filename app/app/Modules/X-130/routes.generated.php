<?php

declare(strict_types=1);

use App\Modules\X130\Ui\CoverageByTrade;
use App\Modules\X130\Ui\DemandTile;
use App\Modules\X130\Ui\PublicIndexPages;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-130')->group(function () {
    Route::get('/demand-tile', DemandTile::class)->name('x-130.demand-tile');
    Route::get('/public-index-pages', PublicIndexPages::class)->name('x-130.public-index-pages');
    Route::get('/coverage-by-trade', CoverageByTrade::class)->name('x-130.coverage-by-trade');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-130')->group(function () {
    Route::get('/demand-tile', DemandTile::class)->name('x-130.demand-tile.admin');
    Route::get('/public-index-pages', PublicIndexPages::class)->name('x-130.public-index-pages.admin');
    Route::get('/coverage-by-trade', CoverageByTrade::class)->name('x-130.coverage-by-trade.admin');
});

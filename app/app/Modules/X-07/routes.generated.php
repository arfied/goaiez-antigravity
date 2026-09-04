<?php

declare(strict_types=1);

use App\Modules\X07\Ui\ForecastRiskTiles;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-07')->group(function () {
    Route::get('/forecast-risk-tiles', ForecastRiskTiles::class)->name('x-07.forecast-risk-tiles');
});

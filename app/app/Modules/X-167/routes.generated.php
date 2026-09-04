<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-167')->group(function () {
    Route::get('/stock-by-van', \App\Modules\X167\Ui\StockByVan::class)->name('x-167.stock-by-van');
    Route::get('/reorders', \App\Modules\X167\Ui\Reorders::class)->name('x-167.reorders');
});


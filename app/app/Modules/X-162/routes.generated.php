<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-162')->group(function () {
    Route::get('/dispatch-board', \App\Modules\X162\Ui\DispatchBoard::class)->name('x-162.dispatch-board');
    Route::get('/map', \App\Modules\X162\Ui\Map::class)->name('x-162.map');
});


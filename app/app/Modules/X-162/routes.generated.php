<?php

declare(strict_types=1);

use App\Modules\X162\Ui\DispatchBoard;
use App\Modules\X162\Ui\Map;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-162')->group(function () {
    Route::get('/dispatch-board', DispatchBoard::class)->name('x-162.dispatch-board');
    Route::get('/map', Map::class)->name('x-162.map');
});

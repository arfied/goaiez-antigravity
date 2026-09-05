<?php

declare(strict_types=1);

use App\Modules\X190\Ui\NetworkMap;
use App\Modules\X190\Ui\PoolDepthPer;
use App\Modules\X190\Ui\SlotBoard;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-190')->group(function () {
    Route::get('/slot-board', SlotBoard::class)->name('x-190.slot-board');
    Route::get('/network-map', NetworkMap::class)->name('x-190.network-map');
    Route::get('/pool-depth-per', PoolDepthPer::class)->name('x-190.pool-depth-per');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-190')->group(function () {
    Route::get('/slot-board', SlotBoard::class)->name('x-190.slot-board.admin');
    Route::get('/network-map', NetworkMap::class)->name('x-190.network-map.admin');
    Route::get('/pool-depth-per', PoolDepthPer::class)->name('x-190.pool-depth-per.admin');
});

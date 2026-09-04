<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-190')->group(function () {
    Route::get('/slot-board', \App\Modules\X190\Ui\SlotBoard::class)->name('x-190.slot-board');
    Route::get('/network-map', \App\Modules\X190\Ui\NetworkMap::class)->name('x-190.network-map');
    Route::get('/pool-depth-per', \App\Modules\X190\Ui\PoolDepthPer::class)->name('x-190.pool-depth-per');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-190')->group(function () {
    Route::get('/slot-board', \App\Modules\X190\Ui\SlotBoard::class)->name('x-190.slot-board.admin');
    Route::get('/network-map', \App\Modules\X190\Ui\NetworkMap::class)->name('x-190.network-map.admin');
    Route::get('/pool-depth-per', \App\Modules\X190\Ui\PoolDepthPer::class)->name('x-190.pool-depth-per.admin');
});


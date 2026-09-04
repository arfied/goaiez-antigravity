<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-190')->group(function () {
    Route::get('/x-190/slot-board', \App\Modules\X190\Ui\SlotBoard::class)->name('x-190.slot-board');
    Route::get('/x-190/network-map', \App\Modules\X190\Ui\NetworkMap::class)->name('x-190.network-map');
    Route::get('/x-190/pool-depth-per', \App\Modules\X190\Ui\PoolDepthPer::class)->name('x-190.pool-depth-per');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-190')->group(function () {
    Route::get('/x-190/slot-board', \App\Modules\X190\Ui\SlotBoard::class)->name('x-190.slot-board');
    Route::get('/x-190/network-map', \App\Modules\X190\Ui\NetworkMap::class)->name('x-190.network-map');
    Route::get('/x-190/pool-depth-per', \App\Modules\X190\Ui\PoolDepthPer::class)->name('x-190.pool-depth-per');
});


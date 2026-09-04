<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X190\Ui\NetworkMap;
use App\Modules\X190\Ui\PoolDepthPer;
use App\Modules\X190\Ui\SlotBoard;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-190')->group(function () {
    Route::get('/x-190/slot-board', SlotBoard::class)->name('x-190.slot-board');
    Route::get('/x-190/network-map', NetworkMap::class)->name('x-190.network-map');
    Route::get('/x-190/pool-depth-per', PoolDepthPer::class)->name('x-190.pool-depth-per');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-190')->group(function () {
    Route::get('/x-190/slot-board', SlotBoard::class)->name('x-190.slot-board');
    Route::get('/x-190/network-map', NetworkMap::class)->name('x-190.network-map');
    Route::get('/x-190/pool-depth-per', PoolDepthPer::class)->name('x-190.pool-depth-per');
});

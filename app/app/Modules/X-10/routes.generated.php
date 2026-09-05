<?php

declare(strict_types=1);

use App\Modules\X10\Ui\RoutingRules;
use App\Modules\X10\Ui\TerritoryMap;
use App\Modules\X10\Ui\UnassignedCount;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-10')->group(function () {
    Route::get('/routing-rules', RoutingRules::class)->name('x-10.routing-rules');
    Route::get('/territory-map', TerritoryMap::class)->name('x-10.territory-map');
    Route::get('/unassigned-count', UnassignedCount::class)->name('x-10.unassigned-count');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-10')->group(function () {
    Route::get('/routing-rules', RoutingRules::class)->name('x-10.routing-rules.admin');
    Route::get('/territory-map', TerritoryMap::class)->name('x-10.territory-map.admin');
    Route::get('/unassigned-count', UnassignedCount::class)->name('x-10.unassigned-count.admin');
});

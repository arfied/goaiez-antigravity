<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-10')->group(function () {
    Route::get('/routing-rules', \App\Modules\X10\Ui\RoutingRules::class)->name('x-10.routing-rules');
    Route::get('/territory-map', \App\Modules\X10\Ui\TerritoryMap::class)->name('x-10.territory-map');
    Route::get('/unassigned-count', \App\Modules\X10\Ui\UnassignedCount::class)->name('x-10.unassigned-count');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-10')->group(function () {
    Route::get('/routing-rules', \App\Modules\X10\Ui\RoutingRules::class)->name('x-10.routing-rules.admin');
    Route::get('/territory-map', \App\Modules\X10\Ui\TerritoryMap::class)->name('x-10.territory-map.admin');
    Route::get('/unassigned-count', \App\Modules\X10\Ui\UnassignedCount::class)->name('x-10.unassigned-count.admin');
});


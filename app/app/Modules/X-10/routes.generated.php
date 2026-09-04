<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X10\Ui\RoutingRules;
use App\Modules\X10\Ui\TerritoryMap;
use App\Modules\X10\Ui\UnassignedCount;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-10')->group(function () {
    Route::get('/x-10/routing-rules', RoutingRules::class)->name('x-10.routing-rules');
    Route::get('/x-10/territory-map', TerritoryMap::class)->name('x-10.territory-map');
    Route::get('/x-10/unassigned-count', UnassignedCount::class)->name('x-10.unassigned-count');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-10')->group(function () {
    Route::get('/x-10/routing-rules', RoutingRules::class)->name('x-10.routing-rules');
    Route::get('/x-10/territory-map', TerritoryMap::class)->name('x-10.territory-map');
    Route::get('/x-10/unassigned-count', UnassignedCount::class)->name('x-10.unassigned-count');
});

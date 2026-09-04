<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

if (! class_exists('TenantRoleMiddleware')) {
    class TenantRoleMiddleware
    {
        public function handle($request, $next) {
            abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
            return $next($request);
        }
    }
}

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-10')->group(function () {
    Route::get('/x-10/routing-rules', \App\Modules\X10\Ui\RoutingRules::class)->name('x-10.routing-rules');
    Route::get('/x-10/territory-map', \App\Modules\X10\Ui\TerritoryMap::class)->name('x-10.territory-map');
    Route::get('/x-10/unassigned-count', \App\Modules\X10\Ui\UnassignedCount::class)->name('x-10.unassigned-count');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-10')->group(function () {
    Route::get('/x-10/routing-rules', \App\Modules\X10\Ui\RoutingRules::class)->name('x-10.routing-rules');
    Route::get('/x-10/territory-map', \App\Modules\X10\Ui\TerritoryMap::class)->name('x-10.territory-map');
    Route::get('/x-10/unassigned-count', \App\Modules\X10\Ui\UnassignedCount::class)->name('x-10.unassigned-count');
});


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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-16')->group(function () {
    Route::get('/x-16/geogrid-map', \App\Modules\X16\Ui\GeogridMap::class)->name('x-16.geogrid-map');
    Route::get('/x-16/servicearea-polygon', \App\Modules\X16\Ui\ServiceareaPolygon::class)->name('x-16.servicearea-polygon');
    Route::get('/x-16/harvest-coverage-by', \App\Modules\X16\Ui\HarvestCoverageBy::class)->name('x-16.harvest-coverage-by');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-16')->group(function () {
    Route::get('/x-16/geogrid-map', \App\Modules\X16\Ui\GeogridMap::class)->name('x-16.geogrid-map');
    Route::get('/x-16/servicearea-polygon', \App\Modules\X16\Ui\ServiceareaPolygon::class)->name('x-16.servicearea-polygon');
    Route::get('/x-16/harvest-coverage-by', \App\Modules\X16\Ui\HarvestCoverageBy::class)->name('x-16.harvest-coverage-by');
});


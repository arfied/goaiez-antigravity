<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-130')->group(function () {
    Route::get('/x-130/demand-tile', \App\Modules\X130\Ui\DemandTile::class)->name('x-130.demand-tile');
    Route::get('/x-130/public-index-pages', \App\Modules\X130\Ui\PublicIndexPages::class)->name('x-130.public-index-pages');
    Route::get('/x-130/coverage-by-trade', \App\Modules\X130\Ui\CoverageByTrade::class)->name('x-130.coverage-by-trade');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-130')->group(function () {
    Route::get('/x-130/demand-tile', \App\Modules\X130\Ui\DemandTile::class)->name('x-130.demand-tile');
    Route::get('/x-130/public-index-pages', \App\Modules\X130\Ui\PublicIndexPages::class)->name('x-130.public-index-pages');
    Route::get('/x-130/coverage-by-trade', \App\Modules\X130\Ui\CoverageByTrade::class)->name('x-130.coverage-by-trade');
});


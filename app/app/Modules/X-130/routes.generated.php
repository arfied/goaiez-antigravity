<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X130\Ui\CoverageByTrade;
use App\Modules\X130\Ui\DemandTile;
use App\Modules\X130\Ui\PublicIndexPages;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-130')->group(function () {
    Route::get('/x-130/demand-tile', DemandTile::class)->name('x-130.demand-tile');
    Route::get('/x-130/public-index-pages', PublicIndexPages::class)->name('x-130.public-index-pages');
    Route::get('/x-130/coverage-by-trade', CoverageByTrade::class)->name('x-130.coverage-by-trade');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-130')->group(function () {
    Route::get('/x-130/demand-tile', DemandTile::class)->name('x-130.demand-tile');
    Route::get('/x-130/public-index-pages', PublicIndexPages::class)->name('x-130.public-index-pages');
    Route::get('/x-130/coverage-by-trade', CoverageByTrade::class)->name('x-130.coverage-by-trade');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-07')->group(function () {
    Route::get('/x-07/forecast-risk-tiles', \App\Modules\X07\Ui\ForecastRiskTiles::class)->name('x-07.forecast-risk-tiles');
});


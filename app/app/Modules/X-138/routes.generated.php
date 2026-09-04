<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-138')->group(function () {
    Route::get('/x-138/attribution-row', \App\Modules\X138\Ui\AttributionRow::class)->name('x-138.attribution-row');
    Route::get('/x-138/roi-dashboard', \App\Modules\X138\Ui\RoiDashboard::class)->name('x-138.roi-dashboard');
});


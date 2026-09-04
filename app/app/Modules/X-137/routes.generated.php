<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-137')->group(function () {
    Route::get('/x-137/attribution-row', \App\Modules\X137\Ui\AttributionRow::class)->name('x-137.attribution-row');
    Route::get('/x-137/dni-pool-utilisation', \App\Modules\X137\Ui\DniPoolUtilisation::class)->name('x-137.dni-pool-utilisation');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-137')->group(function () {
    Route::get('/x-137/attribution-row', \App\Modules\X137\Ui\AttributionRow::class)->name('x-137.attribution-row');
    Route::get('/x-137/dni-pool-utilisation', \App\Modules\X137\Ui\DniPoolUtilisation::class)->name('x-137.dni-pool-utilisation');
});


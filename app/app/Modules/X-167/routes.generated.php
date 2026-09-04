<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-167')->group(function () {
    Route::get('/x-167/stock-by-van', \App\Modules\X167\Ui\StockByVan::class)->name('x-167.stock-by-van');
    Route::get('/x-167/reorders', \App\Modules\X167\Ui\Reorders::class)->name('x-167.reorders');
});


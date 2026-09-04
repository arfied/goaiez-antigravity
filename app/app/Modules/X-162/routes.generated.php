<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-162')->group(function () {
    Route::get('/x-162/dispatch-board', \App\Modules\X162\Ui\DispatchBoard::class)->name('x-162.dispatch-board');
    Route::get('/x-162/map', \App\Modules\X162\Ui\Map::class)->name('x-162.map');
});


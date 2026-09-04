<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X162\Ui\DispatchBoard;
use App\Modules\X162\Ui\Map;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-162')->group(function () {
    Route::get('/x-162/dispatch-board', DispatchBoard::class)->name('x-162.dispatch-board');
    Route::get('/x-162/map', Map::class)->name('x-162.map');
});

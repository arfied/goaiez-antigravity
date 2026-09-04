<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X167\Ui\Reorders;
use App\Modules\X167\Ui\StockByVan;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-167')->group(function () {
    Route::get('/x-167/stock-by-van', StockByVan::class)->name('x-167.stock-by-van');
    Route::get('/x-167/reorders', Reorders::class)->name('x-167.reorders');
});

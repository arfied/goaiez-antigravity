<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X137\Ui\AttributionRow;
use App\Modules\X137\Ui\DniPoolUtilisation;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-137')->group(function () {
    Route::get('/x-137/attribution-row', AttributionRow::class)->name('x-137.attribution-row');
    Route::get('/x-137/dni-pool-utilisation', DniPoolUtilisation::class)->name('x-137.dni-pool-utilisation');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-137')->group(function () {
    Route::get('/x-137/attribution-row', AttributionRow::class)->name('x-137.attribution-row');
    Route::get('/x-137/dni-pool-utilisation', DniPoolUtilisation::class)->name('x-137.dni-pool-utilisation');
});

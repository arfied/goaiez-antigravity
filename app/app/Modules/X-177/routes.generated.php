<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X177\Ui\GbpCard;
use App\Modules\X177\Ui\SuspensionriskEventsFleetwide;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-177')->group(function () {
    Route::get('/x-177/gbp-card', GbpCard::class)->name('x-177.gbp-card');
    Route::get('/x-177/suspensionrisk-events-fleetwide', SuspensionriskEventsFleetwide::class)->name('x-177.suspensionrisk-events-fleetwide');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-177')->group(function () {
    Route::get('/x-177/gbp-card', GbpCard::class)->name('x-177.gbp-card');
    Route::get('/x-177/suspensionrisk-events-fleetwide', SuspensionriskEventsFleetwide::class)->name('x-177.suspensionrisk-events-fleetwide');
});

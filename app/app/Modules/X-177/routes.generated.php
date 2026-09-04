<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-177')->group(function () {
    Route::get('/x-177/gbp-card', \App\Modules\X177\Ui\GbpCard::class)->name('x-177.gbp-card');
    Route::get('/x-177/suspensionrisk-events-fleetwide', \App\Modules\X177\Ui\SuspensionriskEventsFleetwide::class)->name('x-177.suspensionrisk-events-fleetwide');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-177')->group(function () {
    Route::get('/x-177/gbp-card', \App\Modules\X177\Ui\GbpCard::class)->name('x-177.gbp-card');
    Route::get('/x-177/suspensionrisk-events-fleetwide', \App\Modules\X177\Ui\SuspensionriskEventsFleetwide::class)->name('x-177.suspensionrisk-events-fleetwide');
});


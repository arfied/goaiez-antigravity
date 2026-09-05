<?php

declare(strict_types=1);

use App\Modules\X177\Ui\GbpCard;
use App\Modules\X177\Ui\SuspensionriskEventsFleetwide;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-177')->group(function () {
    Route::get('/gbp-card', GbpCard::class)->name('x-177.gbp-card');
    Route::get('/suspensionrisk-events-fleetwide', SuspensionriskEventsFleetwide::class)->name('x-177.suspensionrisk-events-fleetwide');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-177')->group(function () {
    Route::get('/gbp-card', GbpCard::class)->name('x-177.gbp-card.admin');
    Route::get('/suspensionrisk-events-fleetwide', SuspensionriskEventsFleetwide::class)->name('x-177.suspensionrisk-events-fleetwide.admin');
});

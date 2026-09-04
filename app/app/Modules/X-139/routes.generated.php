<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-139')->group(function () {
    Route::get('/adaccount-connect-card', \App\Modules\X139\Ui\AdaccountConnectCard::class)->name('x-139.adaccount-connect-card');
    Route::get('/conversions-pushed-tile', \App\Modules\X139\Ui\ConversionsPushedTile::class)->name('x-139.conversions-pushed-tile');
    Route::get('/rejection-rate', \App\Modules\X139\Ui\RejectionRate::class)->name('x-139.rejection-rate');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-139')->group(function () {
    Route::get('/adaccount-connect-card', \App\Modules\X139\Ui\AdaccountConnectCard::class)->name('x-139.adaccount-connect-card.admin');
    Route::get('/conversions-pushed-tile', \App\Modules\X139\Ui\ConversionsPushedTile::class)->name('x-139.conversions-pushed-tile.admin');
    Route::get('/rejection-rate', \App\Modules\X139\Ui\RejectionRate::class)->name('x-139.rejection-rate.admin');
});


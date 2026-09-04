<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-139')->group(function () {
    Route::get('/x-139/adaccount-connect-card', \App\Modules\X139\Ui\AdaccountConnectCard::class)->name('x-139.adaccount-connect-card');
    Route::get('/x-139/conversions-pushed-tile', \App\Modules\X139\Ui\ConversionsPushedTile::class)->name('x-139.conversions-pushed-tile');
    Route::get('/x-139/rejection-rate', \App\Modules\X139\Ui\RejectionRate::class)->name('x-139.rejection-rate');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-139')->group(function () {
    Route::get('/x-139/adaccount-connect-card', \App\Modules\X139\Ui\AdaccountConnectCard::class)->name('x-139.adaccount-connect-card');
    Route::get('/x-139/conversions-pushed-tile', \App\Modules\X139\Ui\ConversionsPushedTile::class)->name('x-139.conversions-pushed-tile');
    Route::get('/x-139/rejection-rate', \App\Modules\X139\Ui\RejectionRate::class)->name('x-139.rejection-rate');
});


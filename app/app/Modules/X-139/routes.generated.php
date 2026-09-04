<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X139\Ui\AdaccountConnectCard;
use App\Modules\X139\Ui\ConversionsPushedTile;
use App\Modules\X139\Ui\RejectionRate;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-139')->group(function () {
    Route::get('/x-139/adaccount-connect-card', AdaccountConnectCard::class)->name('x-139.adaccount-connect-card');
    Route::get('/x-139/conversions-pushed-tile', ConversionsPushedTile::class)->name('x-139.conversions-pushed-tile');
    Route::get('/x-139/rejection-rate', RejectionRate::class)->name('x-139.rejection-rate');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-139')->group(function () {
    Route::get('/x-139/adaccount-connect-card', AdaccountConnectCard::class)->name('x-139.adaccount-connect-card');
    Route::get('/x-139/conversions-pushed-tile', ConversionsPushedTile::class)->name('x-139.conversions-pushed-tile');
    Route::get('/x-139/rejection-rate', RejectionRate::class)->name('x-139.rejection-rate');
});

<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X198\Ui\ConnectCard;
use App\Modules\X198\Ui\ReconciliationDiscrepancies;
use App\Modules\X198\Ui\SameAccount;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-198')->group(function () {
    Route::get('/x-198/connect-card', ConnectCard::class)->name('x-198.connect-card');
    Route::get('/x-198/same-account', SameAccount::class)->name('x-198.same-account');
    Route::get('/x-198/reconciliation-discrepancies', ReconciliationDiscrepancies::class)->name('x-198.reconciliation-discrepancies');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-198')->group(function () {
    Route::get('/x-198/connect-card', ConnectCard::class)->name('x-198.connect-card');
    Route::get('/x-198/same-account', SameAccount::class)->name('x-198.same-account');
    Route::get('/x-198/reconciliation-discrepancies', ReconciliationDiscrepancies::class)->name('x-198.reconciliation-discrepancies');
});

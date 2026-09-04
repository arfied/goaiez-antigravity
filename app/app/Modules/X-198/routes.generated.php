<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-198')->group(function () {
    Route::get('/x-198/connect-card', \App\Modules\X198\Ui\ConnectCard::class)->name('x-198.connect-card');
    Route::get('/x-198/same-account', \App\Modules\X198\Ui\SameAccount::class)->name('x-198.same-account');
    Route::get('/x-198/reconciliation-discrepancies', \App\Modules\X198\Ui\ReconciliationDiscrepancies::class)->name('x-198.reconciliation-discrepancies');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-198')->group(function () {
    Route::get('/x-198/connect-card', \App\Modules\X198\Ui\ConnectCard::class)->name('x-198.connect-card');
    Route::get('/x-198/same-account', \App\Modules\X198\Ui\SameAccount::class)->name('x-198.same-account');
    Route::get('/x-198/reconciliation-discrepancies', \App\Modules\X198\Ui\ReconciliationDiscrepancies::class)->name('x-198.reconciliation-discrepancies');
});


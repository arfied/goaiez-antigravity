<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-198')->group(function () {
    Route::get('/connect-card', \App\Modules\X198\Ui\ConnectCard::class)->name('x-198.connect-card');
    Route::get('/same-account', \App\Modules\X198\Ui\SameAccount::class)->name('x-198.same-account');
    Route::get('/reconciliation-discrepancies', \App\Modules\X198\Ui\ReconciliationDiscrepancies::class)->name('x-198.reconciliation-discrepancies');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-198')->group(function () {
    Route::get('/connect-card', \App\Modules\X198\Ui\ConnectCard::class)->name('x-198.connect-card.admin');
    Route::get('/same-account', \App\Modules\X198\Ui\SameAccount::class)->name('x-198.same-account.admin');
    Route::get('/reconciliation-discrepancies', \App\Modules\X198\Ui\ReconciliationDiscrepancies::class)->name('x-198.reconciliation-discrepancies.admin');
});


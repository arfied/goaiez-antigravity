<?php

declare(strict_types=1);

use App\Modules\X198\Ui\ConnectCard;
use App\Modules\X198\Ui\ReconciliationDiscrepancies;
use App\Modules\X198\Ui\SameAccount;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-198')->group(function () {
    Route::get('/connect-card', ConnectCard::class)->name('x-198.connect-card');
    Route::get('/connect-card', ConnectCard::class)->name('x-198.connect-card');
    Route::get('/same-account', SameAccount::class)->name('x-198.same-account');
    Route::get('/same-account', SameAccount::class)->name('x-198.same-account');
    Route::get('/reconciliation-discrepancies', ReconciliationDiscrepancies::class)->name('x-198.reconciliation-discrepancies');
    Route::get('/reconciliation-discrepancies', ReconciliationDiscrepancies::class)->name('x-198.reconciliation-discrepancies');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-198')->group(function () {
    Route::get('/connect-card', ConnectCard::class)->name('x-198.connect-card.admin');
    Route::get('/same-account', SameAccount::class)->name('x-198.same-account.admin');
    Route::get('/reconciliation-discrepancies', ReconciliationDiscrepancies::class)->name('x-198.reconciliation-discrepancies.admin');
});

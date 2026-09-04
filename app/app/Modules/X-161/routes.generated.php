<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-161')->group(function () {
    Route::get('/demo-ledger-daily', \App\Modules\X161\Ui\DemoLedgerDaily::class)->name('x-161.demo-ledger-daily.admin');
});


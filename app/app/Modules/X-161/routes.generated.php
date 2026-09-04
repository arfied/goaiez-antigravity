<?php

declare(strict_types=1);

use App\Modules\X161\Ui\DemoLedgerDaily;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-161')->group(function () {
    Route::get('/x-161/demo-ledger-daily', DemoLedgerDaily::class)->name('x-161.demo-ledger-daily');
});

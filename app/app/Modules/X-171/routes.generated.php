<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-171')->group(function () {
    Route::get('/x-171/stafffacing-app', \App\Modules\X171\Ui\StafffacingApp::class)->name('x-171.stafffacing-app');
    Route::get('/x-171/sync-failure-rate', \App\Modules\X171\Ui\SyncFailureRate::class)->name('x-171.sync-failure-rate');
});


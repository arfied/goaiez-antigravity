<?php

declare(strict_types=1);

use App\Modules\X171\Ui\StafffacingApp;
use App\Modules\X171\Ui\SyncFailureRate;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-171')->group(function () {
    Route::get('/x-171/stafffacing-app', StafffacingApp::class)->name('x-171.stafffacing-app');
    Route::get('/x-171/sync-failure-rate', SyncFailureRate::class)->name('x-171.sync-failure-rate');
});

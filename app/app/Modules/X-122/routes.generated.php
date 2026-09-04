<?php

declare(strict_types=1);

use App\Modules\X122\Ui\ActionLog;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-122')->group(function () {
    Route::get('/action-log', ActionLog::class)->name('x-122.action-log');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-122')->group(function () {
    Route::get('/action-log', ActionLog::class)->name('x-122.action-log.admin');
});

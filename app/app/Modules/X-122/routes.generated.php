<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-122')->group(function () {
    Route::get('/action-log', \App\Modules\X122\Ui\ActionLog::class)->name('x-122.action-log.admin');
});


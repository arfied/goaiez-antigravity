<?php

declare(strict_types=1);

use App\Modules\X157\Ui\EdgeStatusPer;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-157')->group(function () {
    Route::get('/edge-status-per', EdgeStatusPer::class)->name('x-157.edge-status-per');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-157')->group(function () {
    Route::get('/edge-status-per', EdgeStatusPer::class)->name('x-157.edge-status-per.admin');
});

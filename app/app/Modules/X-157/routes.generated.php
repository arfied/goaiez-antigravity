<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-157')->group(function () {
    Route::get('/x-157/edge-status-per', \App\Modules\X157\Ui\EdgeStatusPer::class)->name('x-157.edge-status-per');
});


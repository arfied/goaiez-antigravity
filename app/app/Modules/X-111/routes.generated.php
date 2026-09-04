<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-111')->group(function () {
    Route::get('/console', \App\Modules\X111\Ui\Console::class)->name('x-111.console.admin');
});


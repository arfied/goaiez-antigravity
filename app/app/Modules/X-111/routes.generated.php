<?php

declare(strict_types=1);

use App\Modules\X111\Ui\Console;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-111')->group(function () {
    Route::get('/console', Console::class)->name('x-111.console.admin');
});

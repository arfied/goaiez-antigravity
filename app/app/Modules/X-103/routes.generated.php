<?php

declare(strict_types=1);

use App\Modules\X103\Ui\Pages;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-103')->group(function () {
    Route::get('/pages', Pages::class)->name('x-103.pages');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-103')->group(function () {
    Route::get('/pages', Pages::class)->name('x-103.pages.admin');
});

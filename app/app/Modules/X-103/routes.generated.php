<?php

declare(strict_types=1);

use App\Modules\X103\Ui\Pages;
use App\Modules\X103\Ui\SiteInventory;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-103')->group(function () {
    Route::get('/pages', Pages::class)->name('x-103.pages');
    Route::get('/site-inventory', SiteInventory::class)->name('x-103.site-inventory');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-103')->group(function () {
    Route::get('/pages', Pages::class)->name('x-103.pages.admin');
    Route::get('/site-inventory', SiteInventory::class)->name('x-103.site-inventory.admin');
});

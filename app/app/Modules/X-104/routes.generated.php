<?php

declare(strict_types=1);

use App\Modules\X104\Ui\InstallCount;
use App\Modules\X104\Ui\PluginSettingsPage;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-104')->group(function () {
    Route::get('/plugin-settings-page', PluginSettingsPage::class)->name('x-104.plugin-settings-page');
    Route::get('/install-count', InstallCount::class)->name('x-104.install-count');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-104')->group(function () {
    Route::get('/plugin-settings-page', PluginSettingsPage::class)->name('x-104.plugin-settings-page.admin');
    Route::get('/install-count', InstallCount::class)->name('x-104.install-count.admin');
});

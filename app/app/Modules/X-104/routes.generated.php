<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-104')->group(function () {
    Route::get('/plugin-settings-page', \App\Modules\X104\Ui\PluginSettingsPage::class)->name('x-104.plugin-settings-page');
    Route::get('/install-count', \App\Modules\X104\Ui\InstallCount::class)->name('x-104.install-count');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-104')->group(function () {
    Route::get('/plugin-settings-page', \App\Modules\X104\Ui\PluginSettingsPage::class)->name('x-104.plugin-settings-page.admin');
    Route::get('/install-count', \App\Modules\X104\Ui\InstallCount::class)->name('x-104.install-count.admin');
});


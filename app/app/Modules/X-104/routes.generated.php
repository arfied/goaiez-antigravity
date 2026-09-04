<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X104\Ui\InstallCount;
use App\Modules\X104\Ui\PluginSettingsPage;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-104')->group(function () {
    Route::get('/x-104/plugin-settings-page', PluginSettingsPage::class)->name('x-104.plugin-settings-page');
    Route::get('/x-104/install-count', InstallCount::class)->name('x-104.install-count');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-104')->group(function () {
    Route::get('/x-104/plugin-settings-page', PluginSettingsPage::class)->name('x-104.plugin-settings-page');
    Route::get('/x-104/install-count', InstallCount::class)->name('x-104.install-count');
});

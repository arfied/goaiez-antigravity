<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-113')->group(function () {
    Route::get('/x-113/staff', \App\Modules\X113\Ui\Staff::class)->name('x-113.staff');
    Route::get('/x-113/roles', \App\Modules\X113\Ui\Roles::class)->name('x-113.roles');
    Route::get('/x-113/permission-matrix', \App\Modules\X113\Ui\PermissionMatrix::class)->name('x-113.permission-matrix');
    Route::get('/x-113/document-vault', \App\Modules\X113\Ui\DocumentVault::class)->name('x-113.document-vault');
});


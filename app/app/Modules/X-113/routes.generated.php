<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X113\Ui\DocumentVault;
use App\Modules\X113\Ui\PermissionMatrix;
use App\Modules\X113\Ui\Roles;
use App\Modules\X113\Ui\Staff;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-113')->group(function () {
    Route::get('/x-113/staff', Staff::class)->name('x-113.staff');
    Route::get('/x-113/roles', Roles::class)->name('x-113.roles');
    Route::get('/x-113/permission-matrix', PermissionMatrix::class)->name('x-113.permission-matrix');
    Route::get('/x-113/document-vault', DocumentVault::class)->name('x-113.document-vault');
});

<?php

declare(strict_types=1);

use App\Modules\X113\Ui\DocumentVault;
use App\Modules\X113\Ui\PermissionMatrix;
use App\Modules\X113\Ui\Roles;
use App\Modules\X113\Ui\Staff;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-113')->group(function () {
    Route::get('/staff', Staff::class)->name('x-113.staff');
    Route::get('/roles', Roles::class)->name('x-113.roles');
    Route::get('/permission-matrix', PermissionMatrix::class)->name('x-113.permission-matrix');
    Route::get('/document-vault', DocumentVault::class)->name('x-113.document-vault');
});

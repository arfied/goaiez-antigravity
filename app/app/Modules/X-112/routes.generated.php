<?php

declare(strict_types=1);

use App\Modules\X112\Ui\AgencyConsole;
use App\Modules\X112\Ui\ImpersonationLogView;
use App\Modules\X112\Ui\Roles;
use App\Modules\X112\Ui\Staff;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-112')->group(function () {
    Route::get('/agency-console', AgencyConsole::class)->name('x-112.agency-console');
    Route::get('/agency-console', AgencyConsole::class)->name('x-112.agency-console');
    Route::get('/staff', Staff::class)->name('x-112.staff');
    Route::get('/staff', Staff::class)->name('x-112.staff');
    Route::get('/roles', Roles::class)->name('x-112.roles');
    Route::get('/roles', Roles::class)->name('x-112.roles');
    Route::get('/impersonation-log', ImpersonationLogView::class)->name('x-112.impersonation-log');
    Route::get('/impersonation-log', ImpersonationLogView::class)->name('x-112.impersonation-log');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-112')->group(function () {
    Route::get('/agency-console', AgencyConsole::class)->name('x-112.agency-console.admin');
    Route::get('/staff', Staff::class)->name('x-112.staff.admin');
    Route::get('/roles', Roles::class)->name('x-112.roles.admin');
    Route::get('/impersonation-log', ImpersonationLogView::class)->name('x-112.impersonation-log.admin');
});

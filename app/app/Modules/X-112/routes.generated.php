<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X112\Ui\AgencyConsole;
use App\Modules\X112\Ui\ImpersonationLogView;
use App\Modules\X112\Ui\Roles;
use App\Modules\X112\Ui\Staff;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-112')->group(function () {
    Route::get('/x-112/agency-console', AgencyConsole::class)->name('x-112.agency-console');
    Route::get('/x-112/staff', Staff::class)->name('x-112.staff');
    Route::get('/x-112/roles', Roles::class)->name('x-112.roles');
    Route::get('/x-112/impersonation-log', ImpersonationLogView::class)->name('x-112.impersonation-log');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-112')->group(function () {
    Route::get('/x-112/agency-console', AgencyConsole::class)->name('x-112.agency-console');
    Route::get('/x-112/staff', Staff::class)->name('x-112.staff');
    Route::get('/x-112/roles', Roles::class)->name('x-112.roles');
    Route::get('/x-112/impersonation-log', ImpersonationLogView::class)->name('x-112.impersonation-log');
});

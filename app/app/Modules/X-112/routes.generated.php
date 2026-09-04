<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

if (! class_exists('TenantRoleMiddleware')) {
    class TenantRoleMiddleware
    {
        public function handle($request, $next) {
            abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
            return $next($request);
        }
    }
}

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-112')->group(function () {
    Route::get('/agency-console', \App\Modules\X112\Ui\AgencyConsole::class)->name('x-112.agency-console');
    Route::get('/staff', \App\Modules\X112\Ui\Staff::class)->name('x-112.staff');
    Route::get('/roles', \App\Modules\X112\Ui\Roles::class)->name('x-112.roles');
    Route::get('/impersonation-log', \App\Modules\X112\Ui\ImpersonationLogView::class)->name('x-112.impersonation-log');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-112')->group(function () {
    Route::get('/agency-console', \App\Modules\X112\Ui\AgencyConsole::class)->name('x-112.agency-console.admin');
    Route::get('/staff', \App\Modules\X112\Ui\Staff::class)->name('x-112.staff.admin');
    Route::get('/roles', \App\Modules\X112\Ui\Roles::class)->name('x-112.roles.admin');
    Route::get('/impersonation-log', \App\Modules\X112\Ui\ImpersonationLogView::class)->name('x-112.impersonation-log.admin');
});


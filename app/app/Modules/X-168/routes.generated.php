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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-168')->group(function () {
    Route::get('/timesheets', \App\Modules\X168\Ui\TimesheetsView::class)->name('x-168.timesheets');
    Route::get('/approvals', \App\Modules\X168\Ui\ApprovalsView::class)->name('x-168.approvals');
    Route::get('/own-hours', \App\Modules\X168\Ui\OwnHoursView::class)->name('x-168.own-hours');
});


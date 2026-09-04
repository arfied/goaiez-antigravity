<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-168')->group(function () {
    Route::get('/x-168/timesheets', \App\Modules\X168\Ui\TimesheetsView::class)->name('x-168.timesheets');
    Route::get('/x-168/approvals', \App\Modules\X168\Ui\ApprovalsView::class)->name('x-168.approvals');
    Route::get('/x-168/own-hours', \App\Modules\X168\Ui\OwnHoursView::class)->name('x-168.own-hours');
});


<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X168\Ui\ApprovalsView;
use App\Modules\X168\Ui\OwnHoursView;
use App\Modules\X168\Ui\TimesheetsView;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-168')->group(function () {
    Route::get('/x-168/timesheets', TimesheetsView::class)->name('x-168.timesheets');
    Route::get('/x-168/approvals', ApprovalsView::class)->name('x-168.approvals');
    Route::get('/x-168/own-hours', OwnHoursView::class)->name('x-168.own-hours');
});

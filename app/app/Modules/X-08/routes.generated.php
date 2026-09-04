<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X08\Ui\ReasonPerRowView;
use App\Modules\X08\Ui\RiskListView;
use App\Modules\X08\Ui\SortedView;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-08')->group(function () {
    Route::get('/x-08/risk-list', RiskListView::class)->name('x-08.risk-list');
    Route::get('/x-08/sorted', SortedView::class)->name('x-08.sorted');
    Route::get('/x-08/reason-per-row', ReasonPerRowView::class)->name('x-08.reason-per-row');
});

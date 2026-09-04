<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-08')->group(function () {
    Route::get('/x-08/risk-list', \App\Modules\X08\Ui\RiskListView::class)->name('x-08.risk-list');
    Route::get('/x-08/sorted', \App\Modules\X08\Ui\SortedView::class)->name('x-08.sorted');
    Route::get('/x-08/reason-per-row', \App\Modules\X08\Ui\ReasonPerRowView::class)->name('x-08.reason-per-row');
});


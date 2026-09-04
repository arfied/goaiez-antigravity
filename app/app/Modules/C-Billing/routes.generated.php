<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\CBilling\Ui\Credits;
use App\Modules\CBilling\Ui\DunningBoard;
use App\Modules\CBilling\Ui\Mrr;
use App\Modules\CBilling\Ui\RevenueRecovery;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-billing')->group(function () {
    Route::get('/c-billing/credits', Credits::class)->name('c-billing.credits');
    Route::get('/c-billing/mrr', Mrr::class)->name('c-billing.mrr');
    Route::get('/c-billing/revenue-recovery', RevenueRecovery::class)->name('c-billing.revenue-recovery');
    Route::get('/c-billing/dunning-board', DunningBoard::class)->name('c-billing.dunning-board');
});

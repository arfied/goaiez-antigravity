<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-170')->group(function () {
    Route::get('/x-170/commissions', \App\Modules\X170\Ui\Commissions::class)->name('x-170.commissions');
    Route::get('/x-170/scorecard', \App\Modules\X170\Ui\ScorecardUi::class)->name('x-170.scorecard');
});


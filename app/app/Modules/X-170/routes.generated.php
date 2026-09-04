<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X170\Ui\Commissions;
use App\Modules\X170\Ui\ScorecardUi;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-170')->group(function () {
    Route::get('/x-170/commissions', Commissions::class)->name('x-170.commissions');
    Route::get('/x-170/scorecard', ScorecardUi::class)->name('x-170.scorecard');
});

<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X145\Ui\ProposalsAppearToday;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-145')->group(function () {
    Route::get('/x-145/proposals-appear-today', ProposalsAppearToday::class)->name('x-145.proposals-appear-today');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-145')->group(function () {
    Route::get('/x-145/proposals-appear-today', ProposalsAppearToday::class)->name('x-145.proposals-appear-today');
});

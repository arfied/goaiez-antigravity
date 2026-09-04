<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X141\Ui\CounterfactualView;
use App\Modules\X141\Ui\ReplayRuntimeCostView;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-141')->group(function () {
    Route::get('/x-141/counterfactual-view', CounterfactualView::class)->name('x-141.counterfactual-view');
    Route::get('/x-141/replay-runtime-cost', ReplayRuntimeCostView::class)->name('x-141.replay-runtime-cost');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-141')->group(function () {
    Route::get('/x-141/counterfactual-view', CounterfactualView::class)->name('x-141.counterfactual-view');
    Route::get('/x-141/replay-runtime-cost', ReplayRuntimeCostView::class)->name('x-141.replay-runtime-cost');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-141')->group(function () {
    Route::get('/x-141/counterfactual-view', \App\Modules\X141\Ui\CounterfactualView::class)->name('x-141.counterfactual-view');
    Route::get('/x-141/replay-runtime-cost', \App\Modules\X141\Ui\ReplayRuntimeCostView::class)->name('x-141.replay-runtime-cost');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-141')->group(function () {
    Route::get('/x-141/counterfactual-view', \App\Modules\X141\Ui\CounterfactualView::class)->name('x-141.counterfactual-view');
    Route::get('/x-141/replay-runtime-cost', \App\Modules\X141\Ui\ReplayRuntimeCostView::class)->name('x-141.replay-runtime-cost');
});


<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-141')->group(function () {
    Route::get('/counterfactual-view', \App\Modules\X141\Ui\CounterfactualView::class)->name('x-141.counterfactual-view');
    Route::get('/replay-runtime-cost', \App\Modules\X141\Ui\ReplayRuntimeCostView::class)->name('x-141.replay-runtime-cost');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-141')->group(function () {
    Route::get('/counterfactual-view', \App\Modules\X141\Ui\CounterfactualView::class)->name('x-141.counterfactual-view.admin');
    Route::get('/replay-runtime-cost', \App\Modules\X141\Ui\ReplayRuntimeCostView::class)->name('x-141.replay-runtime-cost.admin');
});


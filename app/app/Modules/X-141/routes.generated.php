<?php

declare(strict_types=1);

use App\Modules\X141\Ui\CounterfactualView;
use App\Modules\X141\Ui\ReplayRuntimeCostView;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-141')->group(function () {
    Route::get('/counterfactual-view', CounterfactualView::class)->name('x-141.counterfactual-view');
    Route::get('/replay-runtime-cost', ReplayRuntimeCostView::class)->name('x-141.replay-runtime-cost');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-141')->group(function () {
    Route::get('/counterfactual-view', CounterfactualView::class)->name('x-141.counterfactual-view.admin');
    Route::get('/replay-runtime-cost', ReplayRuntimeCostView::class)->name('x-141.replay-runtime-cost.admin');
});

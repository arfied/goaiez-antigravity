<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-08')->group(function () {
    Route::get('/risk-list', \App\Modules\X08\Ui\RiskListView::class)->name('x-08.risk-list');
    Route::get('/sorted', \App\Modules\X08\Ui\SortedView::class)->name('x-08.sorted');
    Route::get('/reason-per-row', \App\Modules\X08\Ui\ReasonPerRowView::class)->name('x-08.reason-per-row');
});


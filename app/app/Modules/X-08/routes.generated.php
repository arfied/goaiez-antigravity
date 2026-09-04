<?php

declare(strict_types=1);

use App\Modules\X08\Ui\ReasonPerRowView;
use App\Modules\X08\Ui\RiskListView;
use App\Modules\X08\Ui\SortedView;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-08')->group(function () {
    Route::get('/risk-list', RiskListView::class)->name('x-08.risk-list');
    Route::get('/sorted', SortedView::class)->name('x-08.sorted');
    Route::get('/reason-per-row', ReasonPerRowView::class)->name('x-08.reason-per-row');
});

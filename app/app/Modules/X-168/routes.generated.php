<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-168')->group(function () {
    Route::get('/timesheets', \App\Modules\X168\Ui\TimesheetsView::class)->name('x-168.timesheets');
    Route::get('/approvals', \App\Modules\X168\Ui\ApprovalsView::class)->name('x-168.approvals');
    Route::get('/own-hours', \App\Modules\X168\Ui\OwnHoursView::class)->name('x-168.own-hours');
});


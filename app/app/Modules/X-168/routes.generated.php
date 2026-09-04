<?php

declare(strict_types=1);

use App\Modules\X168\Ui\ApprovalsView;
use App\Modules\X168\Ui\OwnHoursView;
use App\Modules\X168\Ui\TimesheetsView;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-168')->group(function () {
    Route::get('/timesheets', TimesheetsView::class)->name('x-168.timesheets');
    Route::get('/approvals', ApprovalsView::class)->name('x-168.approvals');
    Route::get('/own-hours', OwnHoursView::class)->name('x-168.own-hours');
});

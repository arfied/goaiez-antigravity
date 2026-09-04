<?php

declare(strict_types=1);

use App\Modules\X125\Ui\Canvas;
use App\Modules\X125\Ui\FlowErrorDashboard;
use App\Modules\X125\Ui\Runs;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-125')->group(function () {
    Route::get('/canvas', Canvas::class)->name('x-125.canvas');
    Route::get('/runs', Runs::class)->name('x-125.runs');
    Route::get('/flow-error-dashboard', FlowErrorDashboard::class)->name('x-125.flow-error-dashboard');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-125')->group(function () {
    Route::get('/canvas', Canvas::class)->name('x-125.canvas.admin');
    Route::get('/runs', Runs::class)->name('x-125.runs.admin');
    Route::get('/flow-error-dashboard', FlowErrorDashboard::class)->name('x-125.flow-error-dashboard.admin');
});

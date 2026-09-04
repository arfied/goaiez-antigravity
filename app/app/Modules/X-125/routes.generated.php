<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-125')->group(function () {
    Route::get('/canvas', \App\Modules\X125\Ui\Canvas::class)->name('x-125.canvas');
    Route::get('/runs', \App\Modules\X125\Ui\Runs::class)->name('x-125.runs');
    Route::get('/flow-error-dashboard', \App\Modules\X125\Ui\FlowErrorDashboard::class)->name('x-125.flow-error-dashboard');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-125')->group(function () {
    Route::get('/canvas', \App\Modules\X125\Ui\Canvas::class)->name('x-125.canvas.admin');
    Route::get('/runs', \App\Modules\X125\Ui\Runs::class)->name('x-125.runs.admin');
    Route::get('/flow-error-dashboard', \App\Modules\X125\Ui\FlowErrorDashboard::class)->name('x-125.flow-error-dashboard.admin');
});


<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-138')->group(function () {
    Route::get('/attribution-row', \App\Modules\X138\Ui\AttributionRow::class)->name('x-138.attribution-row');
    Route::get('/roi-dashboard', \App\Modules\X138\Ui\RoiDashboard::class)->name('x-138.roi-dashboard');
});


<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-137')->group(function () {
    Route::get('/attribution-row', \App\Modules\X137\Ui\AttributionRow::class)->name('x-137.attribution-row');
    Route::get('/dni-pool-utilisation', \App\Modules\X137\Ui\DniPoolUtilisation::class)->name('x-137.dni-pool-utilisation');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-137')->group(function () {
    Route::get('/attribution-row', \App\Modules\X137\Ui\AttributionRow::class)->name('x-137.attribution-row.admin');
    Route::get('/dni-pool-utilisation', \App\Modules\X137\Ui\DniPoolUtilisation::class)->name('x-137.dni-pool-utilisation.admin');
});


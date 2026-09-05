<?php

declare(strict_types=1);

use App\Modules\X137\Ui\AttributionRow;
use App\Modules\X137\Ui\DniPoolUtilisation;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-137')->group(function () {
    Route::get('/attribution-row', AttributionRow::class)->name('x-137.attribution-row');
    Route::get('/dni-pool-utilisation', DniPoolUtilisation::class)->name('x-137.dni-pool-utilisation');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-137')->group(function () {
    Route::get('/attribution-row', AttributionRow::class)->name('x-137.attribution-row.admin');
    Route::get('/dni-pool-utilisation', DniPoolUtilisation::class)->name('x-137.dni-pool-utilisation.admin');
});

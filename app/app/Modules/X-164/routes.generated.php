<?php

declare(strict_types=1);

use App\Modules\X164\Ui\EstimatesList;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-164')->group(function () {
    Route::get('/estimates-list', EstimatesList::class)->name('x-164.estimates-list');
});

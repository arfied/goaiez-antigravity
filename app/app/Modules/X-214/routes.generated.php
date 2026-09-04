<?php

declare(strict_types=1);

use App\Modules\X214\Ui\SurchargeDisclosure;
use App\Modules\X214\Ui\SurchargeLine;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-214')->group(function () {
    Route::get('/surcharge-line', SurchargeLine::class)->name('x-214.surcharge-line');
    Route::get('/surcharge-disclosure', SurchargeDisclosure::class)->name('x-214.surcharge-disclosure');
});

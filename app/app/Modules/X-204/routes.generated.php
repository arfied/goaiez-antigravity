<?php

declare(strict_types=1);

use App\Modules\X204\Ui\RefusalsByReason;
use App\Modules\X204\Ui\RegisterSlotStates;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-204')->group(function () {
    Route::get('/refusals-by-reason', RefusalsByReason::class)->name('x-204.refusals-by-reason');
    Route::get('/register-slot-states', RegisterSlotStates::class)->name('x-204.register-slot-states');
});

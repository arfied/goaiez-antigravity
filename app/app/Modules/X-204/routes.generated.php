<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-204')->group(function () {
    Route::get('/x-204/refusals-by-reason', \App\Modules\X204\Ui\RefusalsByReason::class)->name('x-204.refusals-by-reason');
    Route::get('/x-204/register-slot-states', \App\Modules\X204\Ui\RegisterSlotStates::class)->name('x-204.register-slot-states');
});


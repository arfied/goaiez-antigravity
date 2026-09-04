<?php

declare(strict_types=1);

use App\Modules\X204\Ui\RefusalsByReason;
use App\Modules\X204\Ui\RegisterSlotStates;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-204')->group(function () {
    Route::get('/x-204/refusals-by-reason', RefusalsByReason::class)->name('x-204.refusals-by-reason');
    Route::get('/x-204/register-slot-states', RegisterSlotStates::class)->name('x-204.register-slot-states');
});

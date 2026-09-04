<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-136')->group(function () {
    Route::get('/cooling', \App\Modules\X136\Ui\CoolingView::class)->name('x-136.cooling');
    Route::get('/signal-volume-precision', \App\Modules\X136\Ui\SignalVolumePrecisionView::class)->name('x-136.signal-volume-precision');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-136')->group(function () {
    Route::get('/cooling', \App\Modules\X136\Ui\CoolingView::class)->name('x-136.cooling.admin');
    Route::get('/signal-volume-precision', \App\Modules\X136\Ui\SignalVolumePrecisionView::class)->name('x-136.signal-volume-precision.admin');
});


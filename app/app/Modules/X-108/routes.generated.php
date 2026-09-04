<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-108')->group(function () {
    Route::get('/calendar', \App\Modules\X108\Ui\Calendar::class)->name('x-108.calendar');
    Route::get('/waitlist', \App\Modules\X108\Ui\Waitlist::class)->name('x-108.waitlist');
});


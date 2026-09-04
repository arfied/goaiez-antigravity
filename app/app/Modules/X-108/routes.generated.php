<?php

declare(strict_types=1);

use App\Modules\X108\Ui\Calendar;
use App\Modules\X108\Ui\Waitlist;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-108')->group(function () {
    Route::get('/calendar', Calendar::class)->name('x-108.calendar');
    Route::get('/waitlist', Waitlist::class)->name('x-108.waitlist');
});

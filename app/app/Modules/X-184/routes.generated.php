<?php

declare(strict_types=1);

use App\Modules\X184\Ui\CalendarView;
use App\Modules\X184\Ui\ContentWeek;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-184')->group(function () {
    Route::get('/content-week', ContentWeek::class)->name('x-184.content-week');
    Route::get('/calendar', CalendarView::class)->name('x-184.calendar');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-181')->group(function () {
    Route::get('/qa-queue-sladueat', \App\Modules\X181\Ui\QaQueueSlaDueAt::class)->name('x-181.qa-queue-sladueat');
    Route::get('/ticket', \App\Modules\X181\Ui\Ticket::class)->name('x-181.ticket');
    Route::get('/resolution', \App\Modules\X181\Ui\Resolution::class)->name('x-181.resolution');
});


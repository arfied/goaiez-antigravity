<?php

declare(strict_types=1);

use App\Modules\X181\Ui\QaQueueSlaDueAt;
use App\Modules\X181\Ui\Resolution;
use App\Modules\X181\Ui\Ticket;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-181')->group(function () {
    Route::get('/qa-queue-sladueat', QaQueueSlaDueAt::class)->name('x-181.qa-queue-sladueat');
    Route::get('/ticket', Ticket::class)->name('x-181.ticket');
    Route::get('/resolution', Resolution::class)->name('x-181.resolution');
});

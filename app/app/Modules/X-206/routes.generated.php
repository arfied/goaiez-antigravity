<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-206')->group(function () {
    Route::get('/connections', \App\Modules\X206\Ui\Connections::class)->name('x-206.connections');
    Route::get('/reveal', \App\Modules\X206\Ui\Reveal::class)->name('x-206.reveal');
    Route::get('/reveal-log', \App\Modules\X206\Ui\RevealLog::class)->name('x-206.reveal-log');
});


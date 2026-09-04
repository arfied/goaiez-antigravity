<?php

declare(strict_types=1);

use App\Modules\X206\Ui\Connections;
use App\Modules\X206\Ui\Reveal;
use App\Modules\X206\Ui\RevealLog;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-206')->group(function () {
    Route::get('/connections', Connections::class)->name('x-206.connections');
    Route::get('/reveal', Reveal::class)->name('x-206.reveal');
    Route::get('/reveal-log', RevealLog::class)->name('x-206.reveal-log');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-208')->group(function () {
    Route::get('/piece-preview', \App\Modules\X208\Ui\PiecePreview::class)->name('x-208.piece-preview');
    Route::get('/cost', \App\Modules\X208\Ui\Cost::class)->name('x-208.cost');
    Route::get('/send-record', \App\Modules\X208\Ui\SendRecord::class)->name('x-208.send-record');
});


<?php

declare(strict_types=1);

use App\Modules\X208\Ui\Cost;
use App\Modules\X208\Ui\PiecePreview;
use App\Modules\X208\Ui\SendRecord;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-208')->group(function () {
    Route::get('/piece-preview', PiecePreview::class)->name('x-208.piece-preview');
    Route::get('/cost', Cost::class)->name('x-208.cost');
    Route::get('/send-record', SendRecord::class)->name('x-208.send-record');
});

<?php

declare(strict_types=1);

use App\Modules\X149\Ui\QualityBoard;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-149')->group(function () {
    Route::get('/quality-board', QualityBoard::class)->name('x-149.quality-board');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-149')->group(function () {
    Route::get('/quality-board', QualityBoard::class)->name('x-149.quality-board.admin');
});

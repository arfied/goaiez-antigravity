<?php

declare(strict_types=1);

use App\Modules\X185\Ui\DigestLine;
use App\Modules\X185\Ui\ExperimentBoard;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-185')->group(function () {
    Route::get('/digest-line', DigestLine::class)->name('x-185.digest-line');
    Route::get('/experiment-board', ExperimentBoard::class)->name('x-185.experiment-board');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-185')->group(function () {
    Route::get('/digest-line', DigestLine::class)->name('x-185.digest-line.admin');
    Route::get('/experiment-board', ExperimentBoard::class)->name('x-185.experiment-board.admin');
});

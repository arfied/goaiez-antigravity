<?php

declare(strict_types=1);

use App\Modules\X105\Ui\PipelineBoard;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-105')->group(function () {
    Route::get('/pipeline-board', PipelineBoard::class)->name('x-105.pipeline-board.admin');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-105')->group(function () {
    Route::get('/x-105/pipeline-board', \App\Modules\X105\Ui\PipelineBoard::class)->name('x-105.pipeline-board');
});


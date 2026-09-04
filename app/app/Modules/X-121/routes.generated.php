<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-121')->group(function () {
    Route::get('/entity-history-viewer', \App\Modules\X121\Ui\EntityHistoryViewer::class)->name('x-121.entity-history-viewer.admin');
    Route::get('/when-x111-renders', \App\Modules\X121\Ui\WhenX111Renders::class)->name('x-121.when-x111-renders.admin');
});


<?php

declare(strict_types=1);

use App\Modules\X121\Ui\EntityHistoryViewer;
use App\Modules\X121\Ui\WhenX111Renders;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-121')->group(function () {
    Route::get('/entity-history-viewer', EntityHistoryViewer::class)->name('x-121.entity-history-viewer.admin');
    Route::get('/when-x111-renders', WhenX111Renders::class)->name('x-121.when-x111-renders.admin');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-128')->group(function () {
    Route::get('/x-128/matrix-view', \App\Modules\X128\Ui\MatrixView::class)->name('x-128.matrix-view');
});


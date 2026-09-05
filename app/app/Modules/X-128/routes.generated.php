<?php

declare(strict_types=1);

use App\Modules\X128\Ui\MatrixView;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-128')->group(function () {
    Route::get('/matrix-view', MatrixView::class)->name('x-128.matrix-view');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-128')->group(function () {
    Route::get('/matrix-view', MatrixView::class)->name('x-128.matrix-view.admin');
});

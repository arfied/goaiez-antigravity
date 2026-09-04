<?php

declare(strict_types=1);

use App\Modules\X128\Ui\MatrixView;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-128')->group(function () {
    Route::get('/x-128/matrix-view', MatrixView::class)->name('x-128.matrix-view');
});

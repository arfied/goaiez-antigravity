<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-197')->group(function () {
    Route::get('/x-197/margin-board', \App\Modules\X197\Ui\MarginBoard::class)->name('x-197.margin-board');
});


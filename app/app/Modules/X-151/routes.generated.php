<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-151')->group(function () {
    Route::get('/fetch-board', \App\Modules\X151\Ui\FetchBoard::class)->name('x-151.fetch-board.admin');
});


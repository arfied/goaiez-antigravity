<?php

declare(strict_types=1);

use App\Modules\X151\Ui\FetchBoard;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-151')->group(function () {
    Route::get('/x-151/fetch-board', FetchBoard::class)->name('x-151.fetch-board');
});

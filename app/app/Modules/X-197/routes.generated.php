<?php

declare(strict_types=1);

use App\Modules\X197\Ui\MarginBoard;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-197')->group(function () {
    Route::get('/x-197/margin-board', MarginBoard::class)->name('x-197.margin-board');
});

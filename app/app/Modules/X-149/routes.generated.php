<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-149')->group(function () {
    Route::get('/x-149/quality-board', \App\Modules\X149\Ui\QualityBoard::class)->name('x-149.quality-board');
});


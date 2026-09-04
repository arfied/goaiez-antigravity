<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-ai')->group(function () {
    Route::get('/model-board', \App\Modules\CAi\Ui\ModelBoard::class)->name('c-ai.model-board');
});


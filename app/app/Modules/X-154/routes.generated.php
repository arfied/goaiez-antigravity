<?php

declare(strict_types=1);

use App\Modules\X154\Ui\ReadbackScreen;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-154')->group(function () {
    Route::get('/readback-screen', ReadbackScreen::class)->name('x-154.readback-screen');
});

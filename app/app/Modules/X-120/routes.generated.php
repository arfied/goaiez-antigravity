<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-120')->group(function () {
    Route::get('/card-screen', \App\Modules\X120\Ui\CardScreen::class)->name('x-120.card-screen');
});


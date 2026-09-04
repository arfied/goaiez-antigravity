<?php

declare(strict_types=1);

use App\Modules\X120\Ui\CardScreen;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-120')->group(function () {
    Route::get('/card-screen', CardScreen::class)->name('x-120.card-screen');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-147')->group(function () {
    Route::get('/degrade-rate-per', \App\Modules\X147\Ui\DegradeRatePer::class)->name('x-147.degrade-rate-per.admin');
});


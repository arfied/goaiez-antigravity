<?php

declare(strict_types=1);

use App\Modules\X147\Ui\DegradeRatePer;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-147')->group(function () {
    Route::get('/degrade-rate-per', DegradeRatePer::class)->name('x-147.degrade-rate-per.admin');
});

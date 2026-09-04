<?php

declare(strict_types=1);

use App\Modules\X193\Ui\QuiethourHolds;
use App\Modules\X193\Ui\Sendsbyclass;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-193')->group(function () {
    Route::get('/sendsbyclass', Sendsbyclass::class)->name('x-193.sendsbyclass.admin');
    Route::get('/quiethour-holds', QuiethourHolds::class)->name('x-193.quiethour-holds.admin');
});

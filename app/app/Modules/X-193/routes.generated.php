<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-193')->group(function () {
    Route::get('/x-193/sendsbyclass', \App\Modules\X193\Ui\Sendsbyclass::class)->name('x-193.sendsbyclass');
    Route::get('/x-193/quiethour-holds', \App\Modules\X193\Ui\QuiethourHolds::class)->name('x-193.quiethour-holds');
});


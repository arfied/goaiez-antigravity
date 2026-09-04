<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-82')->group(function () {
    Route::get('/x-82/rate-registry', \App\Modules\X82\Ui\RateRegistryView::class)->name('x-82.rate-registry');
});


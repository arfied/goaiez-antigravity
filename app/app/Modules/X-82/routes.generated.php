<?php

declare(strict_types=1);

use App\Modules\X82\Ui\RateRegistryView;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-82')->group(function () {
    Route::get('/rate-registry', RateRegistryView::class)->name('x-82.rate-registry.admin');
});

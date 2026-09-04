<?php

declare(strict_types=1);

use App\Modules\X180\Ui\PackBrowser;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-180')->group(function () {
    Route::get('/pack-browser', PackBrowser::class)->name('x-180.pack-browser');
});

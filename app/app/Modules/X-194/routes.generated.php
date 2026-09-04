<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-194')->group(function () {
    Route::get('/any-view-it', \App\Modules\X194\Ui\AnyViewIt::class)->name('x-194.any-view-it');
    Route::get('/saved-views-list', \App\Modules\X194\Ui\SavedViewsList::class)->name('x-194.saved-views-list');
});


<?php

declare(strict_types=1);

use App\Modules\X194\Ui\AnyViewIt;
use App\Modules\X194\Ui\SavedViewsList;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-194')->group(function () {
    Route::get('/any-view-it', AnyViewIt::class)->name('x-194.any-view-it');
    Route::get('/saved-views-list', SavedViewsList::class)->name('x-194.saved-views-list');
});

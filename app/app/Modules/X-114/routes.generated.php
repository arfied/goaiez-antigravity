<?php

declare(strict_types=1);

use App\Modules\X114\Ui\BrandKitView;
use App\Modules\X114\Ui\MediaLibraryView;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-114')->group(function () {
    Route::get('/brand-kit', BrandKitView::class)->name('x-114.brand-kit');
    Route::get('/media-library', MediaLibraryView::class)->name('x-114.media-library');
});

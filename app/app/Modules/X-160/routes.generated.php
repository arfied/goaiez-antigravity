<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-160')->group(function () {
    Route::get('/upload-drop', \App\Modules\X160\Ui\UploadDrop::class)->name('x-160.upload-drop');
    Route::get('/review-screen', \App\Modules\X160\Ui\ReviewScreen::class)->name('x-160.review-screen');
    Route::get('/extraction-error-rate', \App\Modules\X160\Ui\ExtractionErrorRate::class)->name('x-160.extraction-error-rate');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-160')->group(function () {
    Route::get('/upload-drop', \App\Modules\X160\Ui\UploadDrop::class)->name('x-160.upload-drop.admin');
    Route::get('/review-screen', \App\Modules\X160\Ui\ReviewScreen::class)->name('x-160.review-screen.admin');
    Route::get('/extraction-error-rate', \App\Modules\X160\Ui\ExtractionErrorRate::class)->name('x-160.extraction-error-rate.admin');
});


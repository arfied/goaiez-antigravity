<?php

declare(strict_types=1);

use App\Modules\X160\Ui\ExtractionErrorRate;
use App\Modules\X160\Ui\ReviewScreen;
use App\Modules\X160\Ui\UploadDrop;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-160')->group(function () {
    Route::get('/upload-drop', UploadDrop::class)->name('x-160.upload-drop');
    Route::get('/review-screen', ReviewScreen::class)->name('x-160.review-screen');
    Route::get('/extraction-error-rate', ExtractionErrorRate::class)->name('x-160.extraction-error-rate');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-160')->group(function () {
    Route::get('/upload-drop', UploadDrop::class)->name('x-160.upload-drop.admin');
    Route::get('/review-screen', ReviewScreen::class)->name('x-160.review-screen.admin');
    Route::get('/extraction-error-rate', ExtractionErrorRate::class)->name('x-160.extraction-error-rate.admin');
});

<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X160\Ui\ExtractionErrorRate;
use App\Modules\X160\Ui\ReviewScreen;
use App\Modules\X160\Ui\UploadDrop;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-160')->group(function () {
    Route::get('/x-160/upload-drop', UploadDrop::class)->name('x-160.upload-drop');
    Route::get('/x-160/review-screen', ReviewScreen::class)->name('x-160.review-screen');
    Route::get('/x-160/extraction-error-rate', ExtractionErrorRate::class)->name('x-160.extraction-error-rate');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-160')->group(function () {
    Route::get('/x-160/upload-drop', UploadDrop::class)->name('x-160.upload-drop');
    Route::get('/x-160/review-screen', ReviewScreen::class)->name('x-160.review-screen');
    Route::get('/x-160/extraction-error-rate', ExtractionErrorRate::class)->name('x-160.extraction-error-rate');
});

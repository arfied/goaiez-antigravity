<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

if (! class_exists('TenantRoleMiddleware')) {
    class TenantRoleMiddleware
    {
        public function handle($request, $next) {
            abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
            return $next($request);
        }
    }
}

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-160')->group(function () {
    Route::get('/x-160/upload-drop', \App\Modules\X160\Ui\UploadDrop::class)->name('x-160.upload-drop');
    Route::get('/x-160/review-screen', \App\Modules\X160\Ui\ReviewScreen::class)->name('x-160.review-screen');
    Route::get('/x-160/extraction-error-rate', \App\Modules\X160\Ui\ExtractionErrorRate::class)->name('x-160.extraction-error-rate');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-160')->group(function () {
    Route::get('/x-160/upload-drop', \App\Modules\X160\Ui\UploadDrop::class)->name('x-160.upload-drop');
    Route::get('/x-160/review-screen', \App\Modules\X160\Ui\ReviewScreen::class)->name('x-160.review-screen');
    Route::get('/x-160/extraction-error-rate', \App\Modules\X160\Ui\ExtractionErrorRate::class)->name('x-160.extraction-error-rate');
});


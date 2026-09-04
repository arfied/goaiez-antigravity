<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-114')->group(function () {
    Route::get('/x-114/brand-kit', \App\Modules\X114\Ui\BrandKitView::class)->name('x-114.brand-kit');
    Route::get('/x-114/media-library', \App\Modules\X114\Ui\MediaLibraryView::class)->name('x-114.media-library');
});


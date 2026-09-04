<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-189')->group(function () {
    Route::get('/x-189/brand-card-editor', \App\Modules\X189\Ui\BrandCardEditor::class)->name('x-189.brand-card-editor');
    Route::get('/x-189/preview-per-destination', \App\Modules\X189\Ui\PreviewPerDestination::class)->name('x-189.preview-per-destination');
});


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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-189')->group(function () {
    Route::get('/x-189/brand-card-editor', \App\Modules\X189\Ui\BrandCardEditor::class)->name('x-189.brand-card-editor');
    Route::get('/x-189/preview-per-destination', \App\Modules\X189\Ui\PreviewPerDestination::class)->name('x-189.preview-per-destination');
});


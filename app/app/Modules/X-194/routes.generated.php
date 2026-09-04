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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-194')->group(function () {
    Route::get('/any-view-it', \App\Modules\X194\Ui\AnyViewIt::class)->name('x-194.any-view-it');
    Route::get('/saved-views-list', \App\Modules\X194\Ui\SavedViewsList::class)->name('x-194.saved-views-list');
});


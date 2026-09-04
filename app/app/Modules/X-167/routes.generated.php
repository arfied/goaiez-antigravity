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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-167')->group(function () {
    Route::get('/stock-by-van', \App\Modules\X167\Ui\StockByVan::class)->name('x-167.stock-by-van');
    Route::get('/reorders', \App\Modules\X167\Ui\Reorders::class)->name('x-167.reorders');
});


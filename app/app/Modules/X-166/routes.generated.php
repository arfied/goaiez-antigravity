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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-166')->group(function () {
    Route::get('/margin-by-job', \App\Modules\X166\Ui\MarginByJob::class)->name('x-166.margin-by-job');
    Route::get('/by-tech', \App\Modules\X166\Ui\ByTech::class)->name('x-166.by-tech');
    Route::get('/by-service', \App\Modules\X166\Ui\ByService::class)->name('x-166.by-service');
    Route::get('/by-source', \App\Modules\X166\Ui\BySource::class)->name('x-166.by-source');
});


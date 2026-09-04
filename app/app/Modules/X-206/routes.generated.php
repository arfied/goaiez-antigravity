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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-206')->group(function () {
    Route::get('/x-206/connections', \App\Modules\X206\Ui\Connections::class)->name('x-206.connections');
    Route::get('/x-206/reveal', \App\Modules\X206\Ui\Reveal::class)->name('x-206.reveal');
    Route::get('/x-206/reveal-log', \App\Modules\X206\Ui\RevealLog::class)->name('x-206.reveal-log');
});


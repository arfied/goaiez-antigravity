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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-165')->group(function () {
    Route::get('/plans', \App\Modules\X165\Ui\Plans::class)->name('x-165.plans');
    Route::get('/members', \App\Modules\X165\Ui\Members::class)->name('x-165.members');
});


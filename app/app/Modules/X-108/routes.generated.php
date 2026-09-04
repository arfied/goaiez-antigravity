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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-108')->group(function () {
    Route::get('/calendar', \App\Modules\X108\Ui\Calendar::class)->name('x-108.calendar');
    Route::get('/waitlist', \App\Modules\X108\Ui\Waitlist::class)->name('x-108.waitlist');
});


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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-182')->group(function () {
    Route::get('/x-182/connected-accounts', \App\Modules\X182\Ui\ConnectedAccounts::class)->name('x-182.connected-accounts');
    Route::get('/x-182/social-queue', \App\Modules\X182\Ui\SocialQueue::class)->name('x-182.social-queue');
});


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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-145')->group(function () {
    Route::get('/proposals-appear-today', \App\Modules\X145\Ui\ProposalsAppearToday::class)->name('x-145.proposals-appear-today');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-145')->group(function () {
    Route::get('/proposals-appear-today', \App\Modules\X145\Ui\ProposalsAppearToday::class)->name('x-145.proposals-appear-today.admin');
});


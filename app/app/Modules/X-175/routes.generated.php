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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-175')->group(function () {
    Route::get('/stafffacing-assistant-panel', \App\Modules\X175\Ui\StafffacingAssistantPanel::class)->name('x-175.stafffacing-assistant-panel');
    Route::get('/customerfacing-none', \App\Modules\X175\Ui\CustomerfacingNone::class)->name('x-175.customerfacing-none');
    Route::get('/by-design', \App\Modules\X175\Ui\ByDesign::class)->name('x-175.by-design');
});


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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-219')->group(function () {
    Route::get('/roster-admin', \App\Modules\X219\Ui\RosterAdmin::class)->name('x-219.roster-admin');
    Route::get('/assignment-matrix', \App\Modules\X219\Ui\AssignmentMatrix::class)->name('x-219.assignment-matrix');
});


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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-129')->group(function () {
    Route::get('/migration-card', \App\Modules\X129\Ui\MigrationCard::class)->name('x-129.migration-card');
    Route::get('/cutover-queue', \App\Modules\X129\Ui\CutoverQueue::class)->name('x-129.cutover-queue');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-129')->group(function () {
    Route::get('/migration-card', \App\Modules\X129\Ui\MigrationCard::class)->name('x-129.migration-card.admin');
    Route::get('/cutover-queue', \App\Modules\X129\Ui\CutoverQueue::class)->name('x-129.cutover-queue.admin');
});


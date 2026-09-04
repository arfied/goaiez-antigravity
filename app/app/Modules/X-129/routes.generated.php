<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-129')->group(function () {
    Route::get('/x-129/migration-card', \App\Modules\X129\Ui\MigrationCard::class)->name('x-129.migration-card');
    Route::get('/x-129/cutover-queue', \App\Modules\X129\Ui\CutoverQueue::class)->name('x-129.cutover-queue');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-129')->group(function () {
    Route::get('/x-129/migration-card', \App\Modules\X129\Ui\MigrationCard::class)->name('x-129.migration-card');
    Route::get('/x-129/cutover-queue', \App\Modules\X129\Ui\CutoverQueue::class)->name('x-129.cutover-queue');
});


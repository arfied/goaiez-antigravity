<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X129\Ui\CutoverQueue;
use App\Modules\X129\Ui\MigrationCard;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-129')->group(function () {
    Route::get('/x-129/migration-card', MigrationCard::class)->name('x-129.migration-card');
    Route::get('/x-129/cutover-queue', CutoverQueue::class)->name('x-129.cutover-queue');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-129')->group(function () {
    Route::get('/x-129/migration-card', MigrationCard::class)->name('x-129.migration-card');
    Route::get('/x-129/cutover-queue', CutoverQueue::class)->name('x-129.cutover-queue');
});

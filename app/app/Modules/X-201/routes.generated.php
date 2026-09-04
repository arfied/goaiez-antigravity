<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X201\Ui\DisputeCard;
use App\Modules\X201\Ui\DisputeQueue;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-201')->group(function () {
    Route::get('/x-201/dispute-card', DisputeCard::class)->name('x-201.dispute-card');
    Route::get('/x-201/dispute-queue', DisputeQueue::class)->name('x-201.dispute-queue');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-201')->group(function () {
    Route::get('/x-201/dispute-card', DisputeCard::class)->name('x-201.dispute-card');
    Route::get('/x-201/dispute-queue', DisputeQueue::class)->name('x-201.dispute-queue');
});

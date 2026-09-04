<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-201')->group(function () {
    Route::get('/x-201/dispute-card', \App\Modules\X201\Ui\DisputeCard::class)->name('x-201.dispute-card');
    Route::get('/x-201/dispute-queue', \App\Modules\X201\Ui\DisputeQueue::class)->name('x-201.dispute-queue');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-201')->group(function () {
    Route::get('/x-201/dispute-card', \App\Modules\X201\Ui\DisputeCard::class)->name('x-201.dispute-card');
    Route::get('/x-201/dispute-queue', \App\Modules\X201\Ui\DisputeQueue::class)->name('x-201.dispute-queue');
});


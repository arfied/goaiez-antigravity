<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X182\Ui\ConnectedAccounts;
use App\Modules\X182\Ui\SocialQueue;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-182')->group(function () {
    Route::get('/x-182/connected-accounts', ConnectedAccounts::class)->name('x-182.connected-accounts');
    Route::get('/x-182/social-queue', SocialQueue::class)->name('x-182.social-queue');
});

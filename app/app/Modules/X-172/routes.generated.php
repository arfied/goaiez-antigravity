<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X172\Ui\CustomerfacingPortal;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-172')->group(function () {
    Route::get('/x-172/customerfacing-portal', CustomerfacingPortal::class)->name('x-172.customerfacing-portal');
});

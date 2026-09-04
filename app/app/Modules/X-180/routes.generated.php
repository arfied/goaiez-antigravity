<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X180\Ui\PackBrowser;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-180')->group(function () {
    Route::get('/x-180/pack-browser', PackBrowser::class)->name('x-180.pack-browser');
});

<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X206\Ui\Connections;
use App\Modules\X206\Ui\Reveal;
use App\Modules\X206\Ui\RevealLog;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-206')->group(function () {
    Route::get('/x-206/connections', Connections::class)->name('x-206.connections');
    Route::get('/x-206/reveal', Reveal::class)->name('x-206.reveal');
    Route::get('/x-206/reveal-log', RevealLog::class)->name('x-206.reveal-log');
});

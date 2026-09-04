<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\CAi\Ui\ModelBoard;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-ai')->group(function () {
    Route::get('/c-ai/model-board', ModelBoard::class)->name('c-ai.model-board');
});

<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X185\Ui\DigestLine;
use App\Modules\X185\Ui\ExperimentBoard;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-185')->group(function () {
    Route::get('/x-185/digest-line', DigestLine::class)->name('x-185.digest-line');
    Route::get('/x-185/experiment-board', ExperimentBoard::class)->name('x-185.experiment-board');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-185')->group(function () {
    Route::get('/x-185/digest-line', DigestLine::class)->name('x-185.digest-line');
    Route::get('/x-185/experiment-board', ExperimentBoard::class)->name('x-185.experiment-board');
});

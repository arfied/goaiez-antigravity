<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-185')->group(function () {
    Route::get('/x-185/digest-line', \App\Modules\X185\Ui\DigestLine::class)->name('x-185.digest-line');
    Route::get('/x-185/experiment-board', \App\Modules\X185\Ui\ExperimentBoard::class)->name('x-185.experiment-board');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-185')->group(function () {
    Route::get('/x-185/digest-line', \App\Modules\X185\Ui\DigestLine::class)->name('x-185.digest-line');
    Route::get('/x-185/experiment-board', \App\Modules\X185\Ui\ExperimentBoard::class)->name('x-185.experiment-board');
});


<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-125')->group(function () {
    Route::get('/x-125/canvas', \App\Modules\X125\Ui\Canvas::class)->name('x-125.canvas');
    Route::get('/x-125/runs', \App\Modules\X125\Ui\Runs::class)->name('x-125.runs');
    Route::get('/x-125/flow-error-dashboard', \App\Modules\X125\Ui\FlowErrorDashboard::class)->name('x-125.flow-error-dashboard');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-125')->group(function () {
    Route::get('/x-125/canvas', \App\Modules\X125\Ui\Canvas::class)->name('x-125.canvas');
    Route::get('/x-125/runs', \App\Modules\X125\Ui\Runs::class)->name('x-125.runs');
    Route::get('/x-125/flow-error-dashboard', \App\Modules\X125\Ui\FlowErrorDashboard::class)->name('x-125.flow-error-dashboard');
});


<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X125\Ui\Canvas;
use App\Modules\X125\Ui\FlowErrorDashboard;
use App\Modules\X125\Ui\Runs;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-125')->group(function () {
    Route::get('/x-125/canvas', Canvas::class)->name('x-125.canvas');
    Route::get('/x-125/runs', Runs::class)->name('x-125.runs');
    Route::get('/x-125/flow-error-dashboard', FlowErrorDashboard::class)->name('x-125.flow-error-dashboard');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-125')->group(function () {
    Route::get('/x-125/canvas', Canvas::class)->name('x-125.canvas');
    Route::get('/x-125/runs', Runs::class)->name('x-125.runs');
    Route::get('/x-125/flow-error-dashboard', FlowErrorDashboard::class)->name('x-125.flow-error-dashboard');
});

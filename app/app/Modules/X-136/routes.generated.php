<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X136\Ui\CoolingView;
use App\Modules\X136\Ui\SignalVolumePrecisionView;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-136')->group(function () {
    Route::get('/x-136/cooling', CoolingView::class)->name('x-136.cooling');
    Route::get('/x-136/signal-volume-precision', SignalVolumePrecisionView::class)->name('x-136.signal-volume-precision');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-136')->group(function () {
    Route::get('/x-136/cooling', CoolingView::class)->name('x-136.cooling');
    Route::get('/x-136/signal-volume-precision', SignalVolumePrecisionView::class)->name('x-136.signal-volume-precision');
});

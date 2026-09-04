<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-136')->group(function () {
    Route::get('/x-136/cooling', \App\Modules\X136\Ui\CoolingView::class)->name('x-136.cooling');
    Route::get('/x-136/signal-volume-precision', \App\Modules\X136\Ui\SignalVolumePrecisionView::class)->name('x-136.signal-volume-precision');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-136')->group(function () {
    Route::get('/x-136/cooling', \App\Modules\X136\Ui\CoolingView::class)->name('x-136.cooling');
    Route::get('/x-136/signal-volume-precision', \App\Modules\X136\Ui\SignalVolumePrecisionView::class)->name('x-136.signal-volume-precision');
});


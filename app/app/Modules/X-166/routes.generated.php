<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-166')->group(function () {
    Route::get('/x-166/margin-by-job', \App\Modules\X166\Ui\MarginByJob::class)->name('x-166.margin-by-job');
    Route::get('/x-166/by-tech', \App\Modules\X166\Ui\ByTech::class)->name('x-166.by-tech');
    Route::get('/x-166/by-service', \App\Modules\X166\Ui\ByService::class)->name('x-166.by-service');
    Route::get('/x-166/by-source', \App\Modules\X166\Ui\BySource::class)->name('x-166.by-source');
});


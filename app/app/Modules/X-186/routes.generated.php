<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-186')->group(function () {
    Route::get('/x-186/sequence-builder', \App\Modules\X186\Ui\SequenceBuilder::class)->name('x-186.sequence-builder');
    Route::get('/x-186/audience-preview-count', \App\Modules\X186\Ui\AudiencePreviewCount::class)->name('x-186.audience-preview-count');
    Route::get('/x-186/live-run', \App\Modules\X186\Ui\LiveRun::class)->name('x-186.live-run');
    Route::get('/x-186/stop-log', \App\Modules\X186\Ui\StopLog::class)->name('x-186.stop-log');
});


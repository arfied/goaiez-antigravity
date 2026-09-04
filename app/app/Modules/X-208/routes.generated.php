<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-208')->group(function () {
    Route::get('/x-208/piece-preview', \App\Modules\X208\Ui\PiecePreview::class)->name('x-208.piece-preview');
    Route::get('/x-208/cost', \App\Modules\X208\Ui\Cost::class)->name('x-208.cost');
    Route::get('/x-208/send-record', \App\Modules\X208\Ui\SendRecord::class)->name('x-208.send-record');
});


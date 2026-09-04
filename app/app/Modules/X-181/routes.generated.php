<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-181')->group(function () {
    Route::get('/x-181/qa-queue-sladueat', \App\Modules\X181\Ui\QaQueueSlaDueAt::class)->name('x-181.qa-queue-sladueat');
    Route::get('/x-181/ticket', \App\Modules\X181\Ui\Ticket::class)->name('x-181.ticket');
    Route::get('/x-181/resolution', \App\Modules\X181\Ui\Resolution::class)->name('x-181.resolution');
});


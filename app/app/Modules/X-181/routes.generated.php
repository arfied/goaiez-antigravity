<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X181\Ui\QaQueueSlaDueAt;
use App\Modules\X181\Ui\Resolution;
use App\Modules\X181\Ui\Ticket;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-181')->group(function () {
    Route::get('/x-181/qa-queue-sladueat', QaQueueSlaDueAt::class)->name('x-181.qa-queue-sladueat');
    Route::get('/x-181/ticket', Ticket::class)->name('x-181.ticket');
    Route::get('/x-181/resolution', Resolution::class)->name('x-181.resolution');
});

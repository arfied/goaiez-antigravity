<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

if (! class_exists('TenantRoleMiddleware')) {
    class TenantRoleMiddleware
    {
        public function handle($request, $next) {
            abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
            return $next($request);
        }
    }
}

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-181')->group(function () {
    Route::get('/qa-queue-sladueat', \App\Modules\X181\Ui\QaQueueSlaDueAt::class)->name('x-181.qa-queue-sladueat');
    Route::get('/ticket', \App\Modules\X181\Ui\Ticket::class)->name('x-181.ticket');
    Route::get('/resolution', \App\Modules\X181\Ui\Resolution::class)->name('x-181.resolution');
});


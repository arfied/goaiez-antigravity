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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-01')->group(function () {
    Route::get('/thread', \App\Modules\X01\Ui\Thread::class)->name('x-01.thread');
    Route::get('/customers-list', \App\Modules\X01\Ui\CustomersList::class)->name('x-01.customers-list');
    Route::get('/person', \App\Modules\X01\Ui\Person::class)->name('x-01.person');
    Route::get('/history', \App\Modules\X01\Ui\History::class)->name('x-01.history');
    Route::get('/payment-risk', \App\Modules\X01\Ui\PaymentRisk::class)->name('x-01.payment-risk');
});


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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-117')->group(function () {
    Route::get('/x-117/cart-block', \App\Modules\X117\Ui\CartBlock::class)->name('x-117.cart-block');
    Route::get('/x-117/checkout-block', \App\Modules\X117\Ui\CheckoutBlock::class)->name('x-117.checkout-block');
});


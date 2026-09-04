<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-117')->group(function () {
    Route::get('/x-117/cart-block', \App\Modules\X117\Ui\CartBlock::class)->name('x-117.cart-block');
    Route::get('/x-117/checkout-block', \App\Modules\X117\Ui\CheckoutBlock::class)->name('x-117.checkout-block');
});


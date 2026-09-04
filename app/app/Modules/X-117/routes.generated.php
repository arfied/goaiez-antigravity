<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X117\Ui\CartBlock;
use App\Modules\X117\Ui\CheckoutBlock;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-117')->group(function () {
    Route::get('/x-117/cart-block', CartBlock::class)->name('x-117.cart-block');
    Route::get('/x-117/checkout-block', CheckoutBlock::class)->name('x-117.checkout-block');
});

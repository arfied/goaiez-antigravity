<?php

declare(strict_types=1);

use App\Modules\X117\Ui\CartBlock;
use App\Modules\X117\Ui\CheckoutBlock;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-117')->group(function () {
    Route::get('/cart-block', CartBlock::class)->name('x-117.cart-block');
    Route::get('/checkout-block', CheckoutBlock::class)->name('x-117.checkout-block');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-199')->group(function () {
    Route::get('/money-paid-today', \App\Modules\X199\Ui\MoneyPaidToday::class)->name('x-199.money-paid-today');
    Route::get('/unpaid', \App\Modules\X199\Ui\Unpaid::class)->name('x-199.unpaid');
    Route::get('/declines', \App\Modules\X199\Ui\Declines::class)->name('x-199.declines');
    Route::get('/invoices', \App\Modules\X199\Ui\Invoices::class)->name('x-199.invoices');
    Route::get('/credits', \App\Modules\X199\Ui\Credits::class)->name('x-199.credits');
});


<?php

declare(strict_types=1);

use App\Modules\X199\Ui\Credits;
use App\Modules\X199\Ui\Declines;
use App\Modules\X199\Ui\Invoices;
use App\Modules\X199\Ui\MoneyPaidToday;
use App\Modules\X199\Ui\Unpaid;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-199')->group(function () {
    Route::get('/money-paid-today', MoneyPaidToday::class)->name('x-199.money-paid-today');
    Route::get('/unpaid', Unpaid::class)->name('x-199.unpaid');
    Route::get('/declines', Declines::class)->name('x-199.declines');
    Route::get('/invoices', Invoices::class)->name('x-199.invoices');
    Route::get('/credits', Credits::class)->name('x-199.credits');
});

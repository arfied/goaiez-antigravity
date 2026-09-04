<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-199')->group(function () {
    Route::get('/x-199/money-paid-today', \App\Modules\X199\Ui\MoneyPaidToday::class)->name('x-199.money-paid-today');
    Route::get('/x-199/unpaid', \App\Modules\X199\Ui\Unpaid::class)->name('x-199.unpaid');
    Route::get('/x-199/declines', \App\Modules\X199\Ui\Declines::class)->name('x-199.declines');
    Route::get('/x-199/invoices', \App\Modules\X199\Ui\Invoices::class)->name('x-199.invoices');
    Route::get('/x-199/credits', \App\Modules\X199\Ui\Credits::class)->name('x-199.credits');
});


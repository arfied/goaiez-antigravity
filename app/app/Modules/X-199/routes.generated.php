<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X199\Ui\Credits;
use App\Modules\X199\Ui\Declines;
use App\Modules\X199\Ui\Invoices;
use App\Modules\X199\Ui\MoneyPaidToday;
use App\Modules\X199\Ui\Unpaid;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-199')->group(function () {
    Route::get('/x-199/money-paid-today', MoneyPaidToday::class)->name('x-199.money-paid-today');
    Route::get('/x-199/unpaid', Unpaid::class)->name('x-199.unpaid');
    Route::get('/x-199/declines', Declines::class)->name('x-199.declines');
    Route::get('/x-199/invoices', Invoices::class)->name('x-199.invoices');
    Route::get('/x-199/credits', Credits::class)->name('x-199.credits');
});

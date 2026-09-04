<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X01\Ui\CustomersList;
use App\Modules\X01\Ui\History;
use App\Modules\X01\Ui\PaymentRisk;
use App\Modules\X01\Ui\Person;
use App\Modules\X01\Ui\Thread;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-01')->group(function () {
    Route::get('/x-01/thread', Thread::class)->name('x-01.thread');
    Route::get('/x-01/customers-list', CustomersList::class)->name('x-01.customers-list');
    Route::get('/x-01/person', Person::class)->name('x-01.person');
    Route::get('/x-01/history', History::class)->name('x-01.history');
    Route::get('/x-01/payment-risk', PaymentRisk::class)->name('x-01.payment-risk');
});

<?php

declare(strict_types=1);

use App\Modules\X01\Ui\CustomersList;
use App\Modules\X01\Ui\History;
use App\Modules\X01\Ui\PaymentRisk;
use App\Modules\X01\Ui\Person;
use App\Modules\X01\Ui\Thread;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-01')->group(function () {
    Route::get('/thread', Thread::class)->name('x-01.thread');
    Route::get('/customers-list', CustomersList::class)->name('x-01.customers-list');
    Route::get('/person', Person::class)->name('x-01.person');
    Route::get('/history', History::class)->name('x-01.history');
    Route::get('/payment-risk', PaymentRisk::class)->name('x-01.payment-risk');
});

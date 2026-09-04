<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-201')->group(function () {
    Route::get('/dispute-card', \App\Modules\X201\Ui\DisputeCard::class)->name('x-201.dispute-card');
    Route::get('/dispute-queue', \App\Modules\X201\Ui\DisputeQueue::class)->name('x-201.dispute-queue');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-201')->group(function () {
    Route::get('/dispute-card', \App\Modules\X201\Ui\DisputeCard::class)->name('x-201.dispute-card.admin');
    Route::get('/dispute-queue', \App\Modules\X201\Ui\DisputeQueue::class)->name('x-201.dispute-queue.admin');
});


<?php

declare(strict_types=1);

use App\Modules\X201\Ui\DisputeCard;
use App\Modules\X201\Ui\DisputeQueue;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-201')->group(function () {
    Route::get('/dispute-card', DisputeCard::class)->name('x-201.dispute-card');
    Route::get('/dispute-queue', DisputeQueue::class)->name('x-201.dispute-queue');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-201')->group(function () {
    Route::get('/dispute-card', DisputeCard::class)->name('x-201.dispute-card.admin');
    Route::get('/dispute-queue', DisputeQueue::class)->name('x-201.dispute-queue.admin');
});

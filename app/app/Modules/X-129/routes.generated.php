<?php

declare(strict_types=1);

use App\Modules\X129\Ui\CutoverQueue;
use App\Modules\X129\Ui\MigrationCard;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-129')->group(function () {
    Route::get('/migration-card', MigrationCard::class)->name('x-129.migration-card');
    Route::get('/cutover-queue', CutoverQueue::class)->name('x-129.cutover-queue');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-129')->group(function () {
    Route::get('/migration-card', MigrationCard::class)->name('x-129.migration-card.admin');
    Route::get('/cutover-queue', CutoverQueue::class)->name('x-129.cutover-queue.admin');
});

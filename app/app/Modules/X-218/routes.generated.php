<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-218')->group(function () {
    Route::get('/discoveryboard', \App\Modules\X218\Ui\DiscoveryBoard::class)->name('x-218.discovery-board');
    Route::get('/dealtracker', \App\Modules\X218\Ui\DealTracker::class)->name('x-218.deal-tracker');
    Route::get('/deliverableproof', \App\Modules\X218\Ui\DeliverableProof::class)->name('x-218.deliverable-proof');
});


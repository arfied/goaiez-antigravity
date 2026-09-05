<?php

declare(strict_types=1);

use App\Modules\X218\Ui\DealTracker;
use App\Modules\X218\Ui\DeliverableProof;
use App\Modules\X218\Ui\DiscoveryBoard;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-218')->group(function () {
    Route::get('/discoveryboard', DiscoveryBoard::class)->name('x-218.discovery-board');
    Route::get('/dealtracker', DealTracker::class)->name('x-218.deal-tracker');
    Route::get('/deliverableproof', DeliverableProof::class)->name('x-218.deliverable-proof');
});

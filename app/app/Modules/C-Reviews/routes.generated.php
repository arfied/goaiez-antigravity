<?php

declare(strict_types=1);

use App\Modules\CReviews\Ui\LossAlerts;
use App\Modules\CReviews\Ui\QaReport;
use App\Modules\CReviews\Ui\ReviewsQaRequests;
use App\Modules\CReviews\Ui\Tickets;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-reviews')->group(function () {
    Route::get('/reviews-qa-requests', ReviewsQaRequests::class)->name('c-reviews.reviews-qa-requests');
    Route::get('/qa-report', QaReport::class)->name('c-reviews.qa-report');
    Route::get('/tickets', Tickets::class)->name('c-reviews.tickets');
    Route::get('/loss-alerts', LossAlerts::class)->name('c-reviews.loss-alerts');
});

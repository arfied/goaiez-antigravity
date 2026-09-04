<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\CReviews\Ui\LossAlerts;
use App\Modules\CReviews\Ui\QaReport;
use App\Modules\CReviews\Ui\ReviewsQaRequests;
use App\Modules\CReviews\Ui\Tickets;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-reviews')->group(function () {
    Route::get('/c-reviews/reviews-qa-requests', ReviewsQaRequests::class)->name('c-reviews.reviews-qa-requests');
    Route::get('/c-reviews/qa-report', QaReport::class)->name('c-reviews.qa-report');
    Route::get('/c-reviews/tickets', Tickets::class)->name('c-reviews.tickets');
    Route::get('/c-reviews/loss-alerts', LossAlerts::class)->name('c-reviews.loss-alerts');
});

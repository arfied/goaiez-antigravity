<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

if (! class_exists('TenantRoleMiddleware')) {
    class TenantRoleMiddleware
    {
        public function handle($request, $next) {
            abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
            return $next($request);
        }
    }
}

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/c-reviews')->group(function () {
    Route::get('/c-reviews/reviews-qa-requests', \App\Modules\CReviews\Ui\ReviewsQaRequests::class)->name('c-reviews.reviews-qa-requests');
    Route::get('/c-reviews/qa-report', \App\Modules\CReviews\Ui\QaReport::class)->name('c-reviews.qa-report');
    Route::get('/c-reviews/tickets', \App\Modules\CReviews\Ui\Tickets::class)->name('c-reviews.tickets');
    Route::get('/c-reviews/loss-alerts', \App\Modules\CReviews\Ui\LossAlerts::class)->name('c-reviews.loss-alerts');
});


<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-132')->group(function () {
    Route::get('/x-132/person-timeline', \App\Modules\X132\Ui\PersonTimelineView::class)->name('x-132.person-timeline');
    Route::get('/x-132/resolution-rate-confidence', \App\Modules\X132\Ui\ResolutionRateConfidenceView::class)->name('x-132.resolution-rate-confidence');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-132')->group(function () {
    Route::get('/x-132/person-timeline', \App\Modules\X132\Ui\PersonTimelineView::class)->name('x-132.person-timeline');
    Route::get('/x-132/resolution-rate-confidence', \App\Modules\X132\Ui\ResolutionRateConfidenceView::class)->name('x-132.resolution-rate-confidence');
});


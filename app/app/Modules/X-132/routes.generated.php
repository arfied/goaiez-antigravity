<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X132\Ui\PersonTimelineView;
use App\Modules\X132\Ui\ResolutionRateConfidenceView;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-132')->group(function () {
    Route::get('/x-132/person-timeline', PersonTimelineView::class)->name('x-132.person-timeline');
    Route::get('/x-132/resolution-rate-confidence', ResolutionRateConfidenceView::class)->name('x-132.resolution-rate-confidence');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-132')->group(function () {
    Route::get('/x-132/person-timeline', PersonTimelineView::class)->name('x-132.person-timeline');
    Route::get('/x-132/resolution-rate-confidence', ResolutionRateConfidenceView::class)->name('x-132.resolution-rate-confidence');
});

<?php

declare(strict_types=1);

use App\Modules\X132\Ui\PersonTimelineView;
use App\Modules\X132\Ui\ResolutionRateConfidenceView;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-132')->group(function () {
    Route::get('/person-timeline', PersonTimelineView::class)->name('x-132.person-timeline');
    Route::get('/resolution-rate-confidence', ResolutionRateConfidenceView::class)->name('x-132.resolution-rate-confidence');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-132')->group(function () {
    Route::get('/person-timeline', PersonTimelineView::class)->name('x-132.person-timeline.admin');
    Route::get('/resolution-rate-confidence', ResolutionRateConfidenceView::class)->name('x-132.resolution-rate-confidence.admin');
});
